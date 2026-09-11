<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encaissements.
 *
 * Trois partis pris, chacun defendu dans docs/DECISIONS.md :
 *
 *  - Le montant est un ENTIER d'unites mineures (D-038). Jamais un flottant :
 *    un service public qui encaisse ne peut pas deriver d'un centime par
 *    arrondi. La contrainte de type le rend impossible, pas seulement
 *    improbable.
 *  - Le cycle de vie du paiement est SEPARE de celui de la demande (D-040) :
 *    la question « le paiement precede-t-il l'envoi ou la signature ? » n'a
 *    pas de reponse, et ne doit pas etre figee dans le schema.
 *  - Les transitions sont gardees PAR LA BASE, comme celles des demandes, et
 *    refusees sans ligne d'audit correspondante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('reissuance_requests')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();

            // Entier, jamais decimal : voir App\Support\Money.
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('minor_unit')->default(0);

            $table->string('status', 20)->default('pending');
            $table->string('provider', 50);

            // Reference rendue par l'operateur. Unique par operateur : deux
            // paiements ne peuvent pas revendiquer la meme transaction.
            $table->string('provider_reference')->nullable();

            /*
             * Cle d'idempotence.
             *
             * Les rappels d'operateurs de paiement mobile sont rejoues : le
             * meme evenement arrive deux, trois, dix fois. Sans cette cle, un
             * rejeu creerait un second encaissement pour la meme demande.
             */
            $table->string('idempotency_key', 80)->unique();

            $table->string('payer_reference')->nullable();
            $table->json('provider_payload')->nullable();

            $table->timestamp('authorised_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['request_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Un montant negatif n'est pas un encaissement.
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check
            CHECK (amount_minor >= 0)');

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_check
            CHECK (currency REGEXP '^[A-Z]{3}$')");

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check
            CHECK (status IN ('pending','authorised','settled','failed','expired','refunded'))");

        /*
         * Les horodatages disent l'HISTOIRE, pas l'etat courant.
         *
         * Un remboursement suit un encaissement : une fois rembourse, le
         * paiement garde `settled_at`, parce que les fonds ONT ete acquis a
         * cette date. Une equivalence stricte entre l'etat et son horodatage
         * effacerait ce fait — et rendrait la comptabilite infaisable.
         */
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_settled_timestamp_check
            CHECK (status <> 'settled' OR settled_at IS NOT NULL)");

        // Un horodatage d'acquittement n'apparait pas avant l'acquittement.
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_settled_only_when_due_check
            CHECK (settled_at IS NULL OR status IN ('settled', 'refunded'))");

        // Un remboursement implique un encaissement prealable.
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_refunded_timestamp_check
            CHECK (status <> 'refunded' OR (refunded_at IS NOT NULL AND settled_at IS NOT NULL))");

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_refunded_only_when_due_check
            CHECK (refunded_at IS NULL OR status = 'refunded')");

        // Un echec porte un motif : un refus sans raison n'est pas
        // exploitable, ni par l'agent, ni par le citoyen.
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_failure_reason_check
            CHECK (status <> 'failed' OR failure_reason IS NOT NULL)");

        $this->createTransitionGuard();
    }

    /**
     * Meme dispositif que pour les demandes : la base refuse une transition
     * non autorisee, et refuse toute transition sans trace.
     */
    private function createTransitionGuard(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TRIGGER phoenix_guard_payment_status_trigger
        BEFORE UPDATE ON payments
        FOR EACH ROW
        BEGIN
            DECLARE autorisee INT DEFAULT 0;
            DECLARE trace INT DEFAULT 0;
            DECLARE message VARCHAR(255);

            IF NOT (NEW.status <=> OLD.status) THEN

                IF OLD.status IN ('failed', 'expired', 'refunded') THEN
                    SET message = CONCAT(
                        'Transition ', OLD.status, ' -> ', NEW.status,
                        ' refusee : ', OLD.status, ' est un etat terminal (paiement ',
                        OLD.id, ')'
                    );
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
                END IF;

                SET autorisee = (
                    (OLD.status = 'pending'    AND NEW.status IN ('authorised', 'failed', 'expired', 'settled'))
                 OR (OLD.status = 'authorised' AND NEW.status IN ('settled', 'failed', 'expired'))
                 OR (OLD.status = 'settled'    AND NEW.status = 'refunded')
                );

                IF autorisee = 0 THEN
                    SET message = CONCAT(
                        'Transition ', OLD.status, ' -> ', NEW.status,
                        ' interdite pour le paiement ', OLD.id
                    );
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
                END IF;

                SELECT COUNT(*) INTO trace
                FROM audit_logs
                WHERE auditable_type = 'payment'
                  AND auditable_id = OLD.id
                  AND from_status = OLD.status
                  AND to_status = NEW.status;

                IF trace = 0 THEN
                    SET message = CONCAT(
                        'Transition ', OLD.status, ' -> ', NEW.status,
                        ' refusee : aucune ligne d''audit correspondante (paiement ',
                        OLD.id, ')'
                    );
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
                END IF;
            END IF;
        END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS phoenix_guard_payment_status_trigger');
        Schema::dropIfExists('payments');
    }
};
