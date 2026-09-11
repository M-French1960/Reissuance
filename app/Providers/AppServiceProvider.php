<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Security\AccountStatusGate;
use App\Support\Security\DatabaseEngineGuard;
use App\Support\Security\VisibilityScopeGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Avant toute chose : MySQL est le seul moteur supporte, et les
         * connexions que le cadre reinjecte sont elaguees ici.
         *
         * Dans register() et non boot() : une connexion etrangere doit
         * disparaitre avant que quoi que ce soit puisse l'utiliser.
         */
        DatabaseEngineGuard::enforce();
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

        /*
         * Un compte qui n'est pas actif n'est autorise a rien, et cela est
         * pose UNE fois plutot que repete dans chacune des capacites des
         * Policies — ou il suffirait d'un oubli (D-059).
         */
        AccountStatusGate::register();
    }
}
