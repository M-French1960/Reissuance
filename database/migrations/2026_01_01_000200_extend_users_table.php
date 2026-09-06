<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles, rattachement et 2FA.
 *
 * Les contraintes CHECK ci-dessous appliquent en base ce que docs/PERMISSIONS.md
 * exige : un officier sans centre ou un maire sans commune est impossible a
 * creer, y compris par un bug applicatif ou une requete SQL directe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('citizen')->after('email');
            $table->string('status', 16)->default('active')->after('role');
            $table->foreignId('civil_status_center_id')->nullable()->after('status')
                ->constrained('civil_status_centers')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->after('civil_status_center_id')
                ->constrained('communes')->restrictOnDelete();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->index(['role', 'status']);
            $table->index('civil_status_center_id');
            $table->index('commune_id');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('citizen','officer','mayor','admin'))");

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check
            CHECK (status IN ('active','suspended','disabled'))");

        // Un officier a un centre et pas de commune ; un maire l'inverse ;
        // citoyen et administrateur n'ont ni l'un ni l'autre.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_scope_check CHECK (
            (role = 'officer' AND civil_status_center_id IS NOT NULL AND commune_id IS NULL)
            OR (role = 'mayor' AND commune_id IS NOT NULL AND civil_status_center_id IS NULL)
            OR (role IN ('citizen','admin') AND civil_status_center_id IS NULL AND commune_id IS NULL)
        )");

        // 4.1 du brief : 2FA obligatoire pour officier, maire et administrateur.
        // Un compte officiel ne peut pas etre actif sans 2FA confirmee.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_official_2fa_check CHECK (
            role = 'citizen' OR status <> 'active' OR two_factor_confirmed_at IS NOT NULL
        )");
    }

    public function down(): void
    {
        foreach (['users_official_2fa_check', 'users_role_scope_check', 'users_status_check', 'users_role_check'] as $c) {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS {$c}");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('civil_status_center_id');
            $table->dropConstrainedForeignId('commune_id');
            $table->dropColumn([
                'role', 'status', 'two_factor_secret', 'two_factor_confirmed_at',
                'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
