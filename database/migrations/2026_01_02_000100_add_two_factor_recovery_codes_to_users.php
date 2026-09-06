<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complete les colonnes 2FA attendues par Fortify.
 *
 * two_factor_secret et two_factor_confirmed_at existent depuis la migration
 * 2026_01_01_000200, qui porte aussi la contrainte users_official_2fa_check :
 * un compte officier, maire ou administrateur ne peut pas etre actif sans 2FA
 * confirmee (4.1 du brief).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            // Permet d'exiger une rotation, et de tracer une reinitialisation
            // declenchee par un administrateur.
            $table->timestamp('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_recovery_codes', 'password_changed_at']);
        });
    }
};
