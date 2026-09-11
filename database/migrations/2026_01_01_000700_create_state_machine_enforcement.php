<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Application de la machine a etats au niveau des donnees.
 *
 * Le 7 du brief exige qu'une transition interdite echoue au niveau des
 * donnees, pas seulement dans les controleurs. Ce declencheur est la seule
 * barriere qui tienne face a un bug applicatif ou a une requete SQL directe.
 *
 * Table de reference : docs/STATE_MACHINE.md 2.
 */
return new class extends Migration
{
    /**
     * Transitions autorisees, reproduites depuis docs/STATE_MACHINE.md.
     * T8 retenue en option A : le maire retourne a l'officier avec motif.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $transitions = [
        // [depuis, vers, role autorise, reference]
        ['draft', 'pending', 'citizen', 'T2'],
        ['pending', 'under_review', 'officer', 'T3'],
        ['under_review', 'awaiting_signature', 'officer', 'T4'],
        ['under_review', 'rejected', 'officer', 'T5'],
        ['under_review', 'escalated', 'officer', 'T6'],
        ['awaiting_signature', 'signed', 'mayor', 'T7'],
        ['awaiting_signature', 'under_review', 'mayor', 'T8'],
        ['escalated', 'signed', 'mayor', 'T9'],
        ['escalated', 'rejected', 'mayor', 'T10'],
        ['escalated', 'under_review', 'mayor', 'T11'],
    ];

    public function up(): void
    {
        Schema::create('allowed_transitions', function (Blueprint $table) {
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('actor_role', 16);
            $table->string('label', 8);

            $table->primary(['from_status', 'to_status']);
        });

        foreach ($this->transitions as [$from, $to, $role, $label]) {
            DB::table('allowed_transitions')->insert([
                'from_status' => $from,
                'to_status' => $to,
                'actor_role' => $role,
                'label' => $label,
            ]);
        }

        /*
         * MySQL n'a pas de revocation « depuis PUBLIC » : les droits sont
         * accordes nominativement, table par table. Le compte applicatif ne
         * recevra donc jamais INSERT/UPDATE/DELETE sur cette table — voir la
         * migration des droits (D-051).
         */

        /*
         * Le declencheur.
         *
         * MySQL n'a pas de fonction de declencheur separee : le corps est
         * ecrit dans le declencheur lui-meme, et l'erreur est levee par
         * SIGNAL SQLSTATE '45000'.
         *
         * `DB::unprepared` envoie l'instruction telle quelle : les `;`
         * internes ne posent pas de probleme, contrairement au client en
         * ligne de commande qui, lui, decoupe dessus.
         */
        DB::unprepared(<<<'SQL'
        CREATE TRIGGER phoenix_guard_request_status_trigger
        BEFORE UPDATE ON reissuance_requests
        FOR EACH ROW
        BEGIN
            DECLARE autorisee INT DEFAULT 0;
            DECLARE trace INT DEFAULT 0;
            DECLARE message VARCHAR(255);

            -- Le statut ne change pas : rien a verifier.
            IF NOT (NEW.status <=> OLD.status) THEN

                -- signed, rejected et cancelled sont terminaux. Toute reprise
                -- passe par une nouvelle demande liee via supersedes_id.
                IF OLD.status IN ('signed', 'rejected', 'cancelled') THEN
                    SET message = CONCAT(
                        'Transition interdite : ', OLD.status,
                        ' est un etat terminal (demande ', OLD.id, ')'
                    );
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
                END IF;

                SELECT COUNT(*) INTO autorisee
                FROM allowed_transitions
                WHERE from_status = OLD.status AND to_status = NEW.status;

                IF autorisee = 0 THEN
                    SET message = CONCAT(
                        'Transition interdite : ', OLD.status, ' -> ', NEW.status,
                        ' (demande ', OLD.id, ')'
                    );
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
                END IF;

                -- Une transition sans trace d'audit est impossible. L'audit
                -- doit avoir ete ecrit plus tot dans la meme transaction.
                SELECT COUNT(*) INTO trace
                FROM audit_logs
                WHERE auditable_type = 'reissuance_request'
                  AND auditable_id = OLD.id
                  AND from_status = OLD.status
                  AND to_status = NEW.status;

                IF trace = 0 THEN
                    SET message = CONCAT(
                        'Transition ', OLD.status, ' -> ', NEW.status,
                        ' refusee : aucune ligne d''audit correspondante (demande ',
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
        DB::unprepared('DROP TRIGGER IF EXISTS phoenix_guard_request_status_trigger');
        Schema::dropIfExists('allowed_transitions');
    }
};
