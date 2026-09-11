<?php

declare(strict_types=1);

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vue de catalogue des declencheurs, lisible par le compte applicatif.
 *
 * POURQUOI CETTE VUE EXISTE. La page de sante doit pouvoir affirmer que le
 * declencheur de machine a etats est bien en place. Sur MySQL,
 * `information_schema.TRIGGERS` est filtree par les droits du compte qui la
 * lit : sans le droit TRIGGER sur la table, le compte applicatif ne voit
 * simplement rien. Verifie, pas suppose : la page de sante annoncait
 * « ABSENT » sur une base parfaitement saine.
 *
 * Accorder TRIGGER au compte applicatif reglerait l'affichage et ouvrirait
 * exactement la faille qu'on cherche a fermer : ce droit permet DROP TRIGGER,
 * donc la suppression de la seule barriere qui refuse une transition
 * interdite. Hors de question.
 *
 * Une vue en SQL SECURITY DEFINER resout les deux : elle est evaluee avec les
 * droits de son proprietaire (le compte de migration, qui voit les
 * declencheurs), et n'expose que deux colonnes de noms. Le compte applicatif
 * y gagne un droit de lecture, et aucun pouvoir sur les declencheurs — ce que
 * verifie GuardCatalogViewTest.
 */
return new class extends Migration
{
    private const VUE = 'phoenix_guards';

    public function up(): void
    {
        $base = (string) DB::connection()->getDatabaseName();

        DB::unprepared(
            'CREATE OR REPLACE SQL SECURITY DEFINER VIEW '.self::VUE.' AS
             SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS target_table
             FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = '.DB::connection()->getPdo()->quote($base)
        );

        // La vue n'est pas une BASE TABLE : apply() ne la parcourt pas avec
        // les tables. Elle est traitee a part, en lecture seule.
        DB::statement(sprintf(
            'GRANT SELECT ON `%s`.`%s` TO %s',
            $base,
            self::VUE,
            ApplicationPrivileges::applicationAccount(),
        ));
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS '.self::VUE);
    }
};
