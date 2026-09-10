<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureAccountIsActive::class,
            'two-factor' => EnsureTwoFactorIsConfirmed::class,
        ]);

        /*
         * Le rappel de l'operateur de paiement vient d'un serveur tiers : il
         * ne peut pas porter de jeton CSRF. Il est garde par la signature HMAC
         * de son corps brut, verifiee avant toute lecture — c'est la seule
         * route du systeme exemptee, et elle l'est explicitement.
         */
        $middleware->validateCsrfTokens(except: ['rappels/hrskills']);

        // En-tetes de securite sur toutes les reponses (4.5 du brief).
        //
        // EnsureAccountIsActive est pose ici, sur le groupe entier, et non sur
        // le seul groupe de routes applicatives : Fortify enregistre ses
        // propres routes authentifiees, qui echappaient au filtre. Un agent
        // suspendu y gardait acces a sa double authentification — cle secrete,
        // QR code et codes de secours compris — jusqu'a sa deconnexion (R14).
        //
        // Il est sans effet sur un visiteur anonyme, et il precede `auth` :
        // l'utilisateur de session lui suffit.
        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
