<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * La page d'accueil — celle qu'un citoyen voit en premier.
 *
 * POURQUOI UN CONTROLEUR plutot qu'un `Route::view` (D-072). La page doit
 * savoir si l'installation delivre des actes reels ou des documents de
 * demonstration, pour le DIRE des l'accueil. Passer la valeur a
 * `Route::view` la figerait a l'enregistrement des routes, donc au moment de
 * la mise en cache des routes — et l'avertissement mentirait le jour ou la
 * configuration changerait.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            /*
             * L'installation produit-elle des actes qui engagent ?
             *
             * Lu depuis la configuration de l'adaptateur, pas depuis une
             * constante : c'est le prestataire reellement branche qui decide,
             * et lui seul. Tant que c'est l'adaptateur de demonstration,
             * l'accueil doit le dire (D-025, §10 du brief).
             */
            'signatureEngage' => config('phoenix.providers.signature') !== 'fake',
        ]);
    }
}
