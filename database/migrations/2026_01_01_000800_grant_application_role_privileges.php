<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Droits du role applicatif.
 *
 * L'application tourne sous phoenix_app, jamais sous le proprietaire du
 * schema. C'est ce qui rend le journal d'audit reellement inalterable
 * (docs/ARCHITECTURE_LOCAL.md 5.1).
 *
 * Consequence assumee : migrate:fresh echoue avec le role applicatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $app = config('database.connections.pgsql.username');

        if (! $app || ! preg_match('/^[a-z_][a-z0-9_]*$/', $app)) {
            throw new RuntimeException("Nom de role applicatif invalide : {$app}");
        }

        DB::statement("GRANT USAGE ON SCHEMA public TO {$app}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$app}");
        DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO {$app}");

        // Le coeur du dispositif : le journal est en ajout seul.
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM {$app}");

        // Le referentiel des transitions n'est modifiable que par migration.
        DB::statement("REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON allowed_transitions FROM {$app}");

        // Les tables creees ulterieurement heritent des memes droits.
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public
            GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$app}");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public
            GRANT USAGE, SELECT ON SEQUENCES TO {$app}");
    }

    public function down(): void
    {
        $app = config('database.connections.pgsql.username');

        if ($app && preg_match('/^[a-z_][a-z0-9_]*$/', $app)) {
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$app}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$app}");
        }
    }
};
