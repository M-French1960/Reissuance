<?php

declare(strict_types=1);

use App\Support\Database\ApplicationPrivileges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Droits du compte applicatif.
 *
 * L'application tourne sous phoenix_app, jamais sous le proprietaire du
 * schema. C'est ce qui rend le journal d'audit reellement inalterable
 * (docs/ARCHITECTURE_LOCAL.md 5.1).
 *
 * En MySQL, les droits se posent TABLE PAR TABLE : un droit accorde sur la
 * base entiere ne peut pas etre revoque sur une table (D-051). Toute la
 * logique vit dans ApplicationPrivileges, et une migration finale la rejoue
 * une fois toutes les tables creees.
 *
 * Consequence assumee : migrate:fresh echoue avec le compte applicatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        ApplicationPrivileges::apply(DB::getDefaultConnection());
    }

    public function down(): void
    {
        $base = DB::connection()->getDatabaseName();
        $compte = ApplicationPrivileges::applicationAccount();

        DB::statement("REVOKE ALL PRIVILEGES ON `{$base}`.* FROM {$compte}");
        DB::statement('FLUSH PRIVILEGES');
    }
};
