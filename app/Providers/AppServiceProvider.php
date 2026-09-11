<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Security\VisibilityScopeGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * L'application refuse de demarrer si une portee globale de visibilite
         * a disparu. C'est exactement le defaut de D-013, qui n'avait leve
         * aucune erreur PHP et faisait fuir les demandes de tous les centres.
         *
         * Depuis le passage a MySQL, qui n'a pas de securite au niveau des
         * lignes, cette portee est redevenue la SEULE barriere contre une
         * fuite en lecture hors perimetre (D-051, docs/RLS.md 7).
         */
        VisibilityScopeGuard::assertRegistered();
    }
}
