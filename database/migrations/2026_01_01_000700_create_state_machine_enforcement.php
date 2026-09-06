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

        // Personne n'ecrit dans cette table en dehors des migrations.
        DB::statement('REVOKE INSERT, UPDATE, DELETE ON allowed_transitions FROM PUBLIC');

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION phoenix_guard_request_status()
        RETURNS TRIGGER
        LANGUAGE plpgsql
        AS $$
        BEGIN
            -- Le statut ne change pas : rien a verifier.
            IF NEW.status IS NOT DISTINCT FROM OLD.status THEN
                RETURN NEW;
            END IF;

            -- signed et rejected sont terminaux. Toute reprise passe par une
            -- nouvelle demande liee via supersedes_id.
            IF OLD.status IN ('signed', 'rejected') THEN
                RAISE EXCEPTION
                    'Transition interdite : % est un etat terminal (demande %)',
                    OLD.status, OLD.id
                    USING ERRCODE = 'check_violation';
            END IF;

            IF NOT EXISTS (
                SELECT 1 FROM allowed_transitions
                WHERE from_status = OLD.status AND to_status = NEW.status
            ) THEN
                RAISE EXCEPTION
                    'Transition interdite : % -> % (demande %)',
                    OLD.status, NEW.status, OLD.id
                    USING ERRCODE = 'check_violation';
            END IF;

            -- Une transition sans trace d'audit est impossible. L'audit doit
            -- avoir ete ecrit plus tot dans la meme transaction.
            IF NOT EXISTS (
                SELECT 1 FROM audit_logs
                WHERE auditable_type = 'reissuance_request'
                  AND auditable_id = OLD.id
                  AND from_status = OLD.status
                  AND to_status = NEW.status
            ) THEN
                RAISE EXCEPTION
                    'Transition % -> % refusee : aucune ligne d''audit correspondante (demande %)',
                    OLD.status, NEW.status, OLD.id
                    USING ERRCODE = 'check_violation';
            END IF;

            RETURN NEW;
        END;
        $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER phoenix_guard_request_status_trigger
            BEFORE UPDATE ON reissuance_requests
            FOR EACH ROW
            EXECUTE FUNCTION phoenix_guard_request_status();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS phoenix_guard_request_status_trigger ON reissuance_requests');
        DB::unprepared('DROP FUNCTION IF EXISTS phoenix_guard_request_status()');
        Schema::dropIfExists('allowed_transitions');
    }
};
