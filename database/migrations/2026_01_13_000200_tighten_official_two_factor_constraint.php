<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Une 2FA « confirmee » sans secret n'en est pas une.
 *
 * CE QUE LA CONTRAINTE D'ORIGINE LAISSAIT PASSER (D-069). Elle exigeait
 * `two_factor_confirmed_at IS NOT NULL` pour tout compte officiel actif, et
 * rien de plus. Une ligne portant une date de confirmation SANS secret la
 * satisfaisait donc : le compte etait repute protege par une double
 * authentification qui n'existait pas.
 *
 * Ce n'etait pas theorique. Les comptes de demonstration etaient exactement
 * dans cet etat, et les fabriques de test aussi. Le defaut est reste invisible
 * tant que rien n'avait besoin de VERIFIER un code ; il est apparu le jour ou
 * signer un acte l'a exige.
 *
 * La contrainte exige desormais les deux. C'est la meme logique que partout
 * ailleurs dans ce projet : la barriere qui compte est celle que la base
 * applique, pas celle que le code promet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Aucune reprise de donnees a faire ici : un compte officiel actif
        // sans secret est precisement ce qu'on refuse. Les installations qui
        // en portent doivent reconfigurer la 2FA du compte concerne — ce que
        // la migration signale plutot que de le faire a leur place.
        $incoherents = DB::table('users')
            ->where('status', 'active')
            ->whereIn('role', ['officer', 'mayor', 'admin'])
            ->whereNull('two_factor_secret')
            ->count();

        if ($incoherents > 0) {
            throw new RuntimeException(
                "{$incoherents} compte(s) officiel(s) actif(s) portent une double authentification "
                ."confirmee sans secret. Ils ne peuvent pas signer d'acte et leur 2FA est fictive. "
                .'Relancez `php artisan db:seed` sur une base de demonstration, ou suspendez ces '
                .'comptes et faites-les reconfigurer leur 2FA avant de rejouer cette migration.'
            );
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_official_2fa_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_official_2fa_check CHECK (
            role = 'citizen' OR status <> 'active'
            OR (two_factor_confirmed_at IS NOT NULL AND two_factor_secret IS NOT NULL)
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_official_2fa_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_official_2fa_check CHECK (
            role = 'citizen' OR status <> 'active' OR two_factor_confirmed_at IS NOT NULL
        )");
    }
};
