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
        self::dropCheck('reissuance_requests', 'reissuance_requests_status_check');
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
        self::dropCheck('reissuance_requests', 'reissuance_requests_submitted_scope_check');
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
         * Le declencheur n'est PAS recree ici.
         *
         * En PostgreSQL, cette migration remplacait la fonction du declencheur
         * pour y ajouter `cancelled` a la liste des etats terminaux. En MySQL,
         * un declencheur ne se remplace pas : il se supprime et se recree. Ces
         * migrations n'ayant jamais tourne en production, la liste est posee
         * directement dans 2026_01_01_000700 — une sequence fraiche est plus
         * lisible qu'un empilement de correctifs (D-051).
         */

        DB::table('allowed_transitions')->insert([
            ['from_status' => 'draft', 'to_status' => 'cancelled', 'actor_role' => 'citizen', 'label' => 'T13'],
            ['from_status' => 'pending', 'to_status' => 'cancelled', 'actor_role' => 'citizen', 'label' => 'T14'],
        ]);
    }

    /**
     * Supprime une contrainte CHECK si elle existe.
     *
     * `DROP CONSTRAINT IF EXISTS` est propre a MariaDB ; MySQL ne l'accepte
     * pas. On interroge donc information_schema, ce qui marche sur les deux.
     */
    public static function dropCheck(string $table, string $contrainte): void
    {
        $existe = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $contrainte]
        );

        if ($existe !== null) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$contrainte}`");
        }
    }

    public function down(): void
    {
        DB::table('allowed_transitions')->whereIn('label', ['T13', 'T14'])->delete();

        self::dropCheck('reissuance_requests', 'reissuance_requests_status_check');
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_status_check
            CHECK (status IN ('draft','pending','under_review','awaiting_signature','escalated','signed','rejected'))");

        self::dropCheck('reissuance_requests', 'reissuance_requests_submitted_scope_check');
        DB::statement("ALTER TABLE reissuance_requests ADD CONSTRAINT reissuance_requests_submitted_scope_check
            CHECK (
                status = 'draft'
                OR (civil_status_center_id IS NOT NULL AND commune_id IS NOT NULL AND submitted_at IS NOT NULL)
            )");
    }
};
