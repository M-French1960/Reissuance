<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impose la 2FA aux roles officiels (4.1 du brief).
 *
 * La contrainte users_official_2fa_check empeche deja un compte officiel actif
 * d'exister sans 2FA confirmee. Ce middleware couvre le cas transitoire : un
 * compte cree par un administrateur, pas encore actif, qui doit poser sa 2FA
 * avant d'acceder a quoi que ce soit.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->role->requiresTwoFactor()
            && $user->two_factor_confirmed_at === null) {
            return redirect()->route('two-factor.setup')->with(
                'status',
                'Votre rôle exige une double authentification. Configurez-la pour continuer.'
            );
        }

        return $next($request);
    }
}
