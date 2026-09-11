<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsDatabaseGrants;
use Tests\TestCase;

/**
 * Les droits du compte applicatif, table par table.
 *
 * POURQUOI CE FICHIER EXISTE. Sur PostgreSQL, ALTER DEFAULT PRIVILEGES donnait
 * automatiquement ses droits a toute table creee ensuite. MySQL n'a rien de
 * tel : une table ajoutee par une migration future ne recoit AUCUN droit, et
 * l'application echoue au premier acces — ou, si quelqu'un corrige en
 * accordant les droits sur la base entiere, le journal d'audit redevient
 * silencieusement modifiable, puisque les droits de base et de table
 * s'additionnent (D-051).
 *
 * Ces tests sont donc le filet : ils echouent le jour ou une migration cree
 * une table sans que `ApplicationPrivileges` ne la couvre.
 */
class DatabasePrivilegesTest extends TestCase
{
    use ReadsDatabaseGrants;

    #[Test]
    public function aucune_table_n_a_ete_oubliee_par_la_migration_des_droits(): void
    {
        $couvertes = array_keys(self::droitsParTable());
        $oubliees = array_values(array_diff(self::tablesDeLaBase(), $couvertes));

        $this->assertSame(
            [],
            $oubliees,
            'Tables sans droits pour le compte applicatif : '.implode(', ', $oubliees)
                ."\nAjoutez-les via `php artisan phoenix:droits` ou une migration de rafraichissement."
        );
    }

    #[Test]
    public function le_journal_d_audit_est_en_ajout_seul(): void
    {
        foreach (ApplicationPrivileges::APPEND_ONLY as $table) {
            $droits = self::droitsParTable()[$table] ?? [];

            $this->assertSame(
                ['SELECT', 'INSERT'],
                $droits,
                "La table {$table} doit être en ajout seul, et rien d'autre."
            );
        }
    }

    #[Test]
    public function le_referentiel_des_transitions_est_en_lecture_seule(): void
    {
        foreach (ApplicationPrivileges::READ_ONLY as $table) {
            $this->assertSame(
                ['SELECT'],
                self::droitsParTable()[$table] ?? [],
                "La table {$table} doit être en lecture seule."
            );
        }
    }

    #[Test]
    public function les_autres_tables_ont_bien_les_quatre_droits(): void
    {
        $particulieres = [...ApplicationPrivileges::APPEND_ONLY, ...ApplicationPrivileges::READ_ONLY];

        foreach (self::droitsParTable() as $table => $droits) {
            if (in_array($table, $particulieres, true) || in_array($table, ApplicationPrivileges::READABLE_VIEWS, true)) {
                continue;
            }

            $this->assertSame(
                ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
                $droits,
                "La table {$table} n'a pas les droits attendus pour l'application."
            );
        }
    }

    /**
     * Le compte applicatif ne doit pas pouvoir toucher aux declencheurs.
     *
     * Le droit TRIGGER permet DROP TRIGGER : l'accorder reviendrait a laisser
     * l'application supprimer la seule barriere qui refuse une transition
     * interdite. La page de sante lit donc la vue phoenix_guards, qui expose
     * les noms sans accorder ce droit (D-052).
     */
    #[Test]
    public function le_compte_applicatif_ne_peut_pas_supprimer_un_declencheur(): void
    {
        $noms = DB::select('SELECT name FROM phoenix_guards');

        $this->assertNotEmpty($noms, 'La vue phoenix_guards devrait lister les déclencheurs.');

        foreach (self::droitsParTable() as $table => $droits) {
            $this->assertNotContains(
                'TRIGGER',
                $droits,
                "Le compte applicatif a le droit TRIGGER sur {$table} : il pourrait supprimer la garde."
            );
        }
    }
}
