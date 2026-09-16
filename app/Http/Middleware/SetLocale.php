<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the language for this request.
 *
 * Order of precedence, strongest first:
 *
 *   1. the signed-in account's saved preference, because someone who set it
 *      once should not have to set it again on another device;
 *   2. the session, so a visitor who switches language keeps it while they
 *      fill in a form;
 *   3. the browser's Accept-Language, so a first visit lands in a language
 *      the person is likely to read;
 *   4. the platform default.
 *
 * A saved preference beats the session on purpose. The alternative — letting
 * a stale session override an account setting — means an agent who set French
 * on their own machine gets English at a shared counter, with no clue why.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $choisie = Locales::orDefault(
            $request->user()?->locale
                ?? $request->session()->get('locale')
                ?? Locales::fromBrowser($request->getLanguages())
        );

        app()->setLocale($choisie);

        // Dates, month names and number formats follow the text. Without this
        // an English page prints "16 septembre 2026".
        setlocale(LC_TIME, $choisie);

        return $next($request);
    }
}
