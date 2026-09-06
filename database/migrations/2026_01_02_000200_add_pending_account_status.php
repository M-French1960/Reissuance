<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le statut « pending » (compte cree, en attente de configuration).
 *
 * Sans lui, le parcours de creation d'un compte officiel est bloque :
 *   - un compte officiel actif sans 2FA est interdit par
 *     users_official_2fa_check, donc le compte est cree desactive ;
 *   - mais un compte desactive ne peut pas se connecter (EnsureAccountIsActive) ;
 *   - donc son titulaire ne peut jamais configurer sa 2FA ;
 *   - donc l'administrateur ne peut jamais l'activer.
 *
 * « pending » ouvre exactement la porte necessaire : se connecter pour poser
 * sa 2FA, et rien d'autre. Le middleware EnsureAccountIsActive restreint ce
 * statut aux seules routes de configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check
            CHECK (status IN ('pending','active','suspended','disabled'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET status = 'disabled' WHERE status = 'pending'");
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check
            CHECK (status IN ('active','suspended','disabled'))");
    }
};
