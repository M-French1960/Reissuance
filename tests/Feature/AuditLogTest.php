<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\ReadsDatabaseGrants;
use Tests\TestCase;

/**
 * Test R12 de docs/PERMISSIONS.md.
 *
 * L'inalterabilite doit tenir au niveau des droits MySQL, pas seulement
 * dans le modele : une exception PHP se contourne, une revocation non.
 */
class AuditLogTest extends TestCase
{
    use ReadsDatabaseGrants;

    #[Test]
    public function une_entree_peut_etre_ajoutee(): void
    {
        $log = AuditLog::create(['action' => 'test.append']);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'test.append']);
    }

    #[Test]
    public function le_modele_refuse_la_modification(): void
    {
        $log = AuditLog::create(['action' => 'test.immutable']);

        $this->expectException(RuntimeException::class);
        $log->update(['action' => 'falsifie']);
    }

    #[Test]
    public function le_modele_refuse_la_suppression(): void
    {
        $log = AuditLog::create(['action' => 'test.immutable']);

        $this->expectException(RuntimeException::class);
        $log->delete();
    }

    /** La barriere qui compte : en contournant totalement le modele. */
    #[Test]
    public function la_base_refuse_un_update_direct_par_le_role_applicatif(): void
    {
        $log = AuditLog::create(['action' => 'test.sql']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/UPDATE command denied/i');

        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'falsifie']);
    }

    #[Test]
    public function la_base_refuse_un_delete_direct_par_le_role_applicatif(): void
    {
        $log = AuditLog::create(['action' => 'test.sql']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/DELETE command denied/i');

        DB::table('audit_logs')->where('id', $log->id)->delete();
    }

    /**
     * Les droits eux-memes, et pas seulement leur effet.
     *
     * MySQL n'a pas d'equivalent de has_table_privilege(). On lit SHOW GRANTS
     * pour le compte courant : c'est la seule source qui reunisse les trois
     * portees (globale, base, table), et elles s'ADDITIONNENT — un droit de
     * base rendrait le journal alterable malgre un droit de table correct
     * (D-051).
     */
    #[Test]
    public function le_role_applicatif_n_a_ni_update_ni_delete_sur_le_journal(): void
    {
        $droits = self::droitsParTable()['audit_logs'] ?? [];

        $this->assertContains('SELECT', $droits, 'Le rôle applicatif doit pouvoir lire le journal.');
        $this->assertContains('INSERT', $droits, 'Le rôle applicatif doit pouvoir écrire au journal.');
        $this->assertNotContains('UPDATE', $droits, 'Le rôle applicatif ne doit pas pouvoir modifier le journal.');
        $this->assertNotContains('DELETE', $droits, 'Le rôle applicatif ne doit pas pouvoir supprimer du journal.');
    }

    /**
     * Aucun droit de base ne doit exister : il s'ajouterait a ceux de table.
     *
     * Ce test est le garde-fou de la remarque ci-dessus. Sans lui, un
     * `GRANT ALL ON phoenix.*` ajoute par commodite passerait inapercu et
     * rendrait le journal alterable, alors que le droit de table
     * `audit_logs` resterait, lui, parfaitement correct.
     */
    #[Test]
    public function le_role_applicatif_n_a_aucun_droit_a_l_echelle_de_la_base(): void
    {
        foreach (self::lignesDeGrants() as $ligne) {
            // `ON `base`.*` : une portee de base. `ON *.*` n'en est pas une —
            // c'est la portee globale, et MySQL y inscrit toujours un
            // `GRANT USAGE` qui, lui, n'accorde rien.
            $this->assertDoesNotMatchRegularExpression(
                '/ ON `[^`]+`\.\* TO /',
                $ligne,
                "Droit accordé à l'échelle de la base : {$ligne}"
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^GRANT (?!USAGE ON \*\.\*)[A-Z, ]+ ON \*\.\* TO /',
                $ligne,
                "Droit accordé à l'échelle du serveur : {$ligne}"
            );
        }
    }
}
