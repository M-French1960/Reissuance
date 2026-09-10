<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « Cancel Request » du diagramme de cas d'utilisation.
 *
 * Deux transitions, T13 et T14, toutes deux réservées au demandeur :
 *
 *   T13  draft   → cancelled
 *   T14  pending → cancelled
 *
 * PAS au-delà de `pending` : une fois qu'un officier a pris le dossier en
 * charge, l'annuler jetterait son travail. Le demandeur passe alors par
 * « Contact Officer », l'autre cas du diagramme. Voir docs/CAS_USAGE.md §4.5.
 *
 * `cancelled` est TERMINAL et distinct de `rejected` : un rejet est une
 * décision de l'administration, une annulation un retrait du demandeur.
 * Les confondre fausserait toute lecture du journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La contrainte enumere les etats : elle doit connaitre le nouveau.
        DB::statement('ALTER TABLE reissuance_requests DROP CONSTRAINT IF EXISTS reissuance_requests_status_check');
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_status_check
            CHECK (status IN ('draft','pending','under_review','awaiting_signature','escalated','signed','rejected','cancelled'))");

        /*
         * La contrainte de perimetre exigeait `submitted_at` des qu'on quitte
         * `draft`. Or un brouillon annule (T13) n'a JAMAIS ete envoye : il n'a
         * pas de date d'envoi, et pas non plus de centre.
         *
         * On ne desserre pas pour autant : une demande annulee APRES envoi
         * (T14) doit toujours porter son centre et sa commune. La contrainte
         * distingue donc les deux cas au lieu d'exempter `cancelled` en bloc.
         */
        DB::statement('ALTER TABLE reissuance_requests DROP CONSTRAINT IF EXISTS reissuance_requests_submitted_scope_check');
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_submitted_scope_check
            CHECK (
                status = 'draft'
                OR (
                    status = 'cancelled'
                    AND (
                        submitted_at IS NULL
                        OR (civil_status_center_id IS NOT NULL AND commune_id IS NOT NULL)
                    )
                )
                OR (
                    civil_status_center_id IS NOT NULL
                    AND commune_id IS NOT NULL
                    AND submitted_at IS NOT NULL
                )
            )");

        /*
         * Le declencheur connait la liste des etats terminaux en dur. Sans
         * cette mise a jour, `cancelled` n'y figurerait pas : une sortie
         * d'annulation ne serait refusee que parce qu'elle n'est pas dans
         * allowed_transitions — donc une ligne ajoutee par erreur dans cette
         * table suffirait a faire repartir une demande annulee. La barriere
         * doit tenir par elle-meme.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION phoenix_guard_request_status()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                -- Le statut ne change pas : rien a verifier.
                IF NEW.status IS NOT DISTINCT FROM OLD.status THEN
                    RETURN NEW;
                END IF;

                -- signed, rejected et cancelled sont terminaux. Toute reprise
                -- passe par une nouvelle demande liee via supersedes_id.
                IF OLD.status IN ('signed', 'rejected', 'cancelled') THEN
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

                -- Une transition sans trace d'audit est impossible. L'audit
                -- doit avoir ete ecrit plus tot dans la meme transaction.
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

        DB::table('allowed_transitions')->insert([
            ['from_status' => 'draft', 'to_status' => 'cancelled', 'actor_role' => 'citizen', 'label' => 'T13'],
            ['from_status' => 'pending', 'to_status' => 'cancelled', 'actor_role' => 'citizen', 'label' => 'T14'],
        ]);
    }

    public function down(): void
    {
        DB::table('allowed_transitions')->whereIn('label', ['T13', 'T14'])->delete();

        DB::statement('ALTER TABLE reissuance_requests DROP CONSTRAINT IF EXISTS reissuance_requests_status_check');
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_status_check
            CHECK (status IN ('draft','pending','under_review','awaiting_signature','escalated','signed','rejected'))");

        DB::statement('ALTER TABLE reissuance_requests DROP CONSTRAINT IF EXISTS reissuance_requests_submitted_scope_check');
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_submitted_scope_check
            CHECK (
                status = 'draft'
                OR (civil_status_center_id IS NOT NULL AND commune_id IS NOT NULL AND submitted_at IS NOT NULL)
            )");
    }
};
