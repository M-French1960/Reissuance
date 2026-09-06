<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Test R12 de docs/PERMISSIONS.md.
 *
 * L'inalterabilite doit tenir au niveau des droits PostgreSQL, pas seulement
 * dans le modele : une exception PHP se contourne, une revocation non.
 */
class AuditLogTest extends TestCase
{
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
        $this->expectExceptionMessageMatches('/permission denied/i');

        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'falsifie']);
    }

    #[Test]
    public function la_base_refuse_un_delete_direct_par_le_role_applicatif(): void
    {
        $log = AuditLog::create(['action' => 'test.sql']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/permission denied/i');

        DB::table('audit_logs')->where('id', $log->id)->delete();
    }

    #[Test]
    public function le_role_applicatif_n_a_ni_update_ni_delete_sur_le_journal(): void
    {
        $role = config('database.connections.pgsql.username');

        $this->assertFalse(
            DB::selectOne("SELECT has_table_privilege(?, 'audit_logs', 'UPDATE') AS g", [$role])->g,
            'Le rôle applicatif ne doit pas pouvoir modifier le journal.'
        );
        $this->assertFalse(
            DB::selectOne("SELECT has_table_privilege(?, 'audit_logs', 'DELETE') AS g", [$role])->g,
            'Le rôle applicatif ne doit pas pouvoir supprimer du journal.'
        );
        $this->assertTrue(
            DB::selectOne("SELECT has_table_privilege(?, 'audit_logs', 'INSERT') AS g", [$role])->g,
            'Le rôle applicatif doit pouvoir écrire au journal.'
        );
    }
}
