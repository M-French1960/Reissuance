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
 *   1. a language CHOSEN in this session, because someone who just picked one
 *      picked it for a reason;
 *   2. the signed-in account's saved preference, so a choice made once carries
 *      to another device and to the queued jobs that send e-mail;
 *   3. the browser's Accept-Language, so a first visit lands in a language the
 *      person is likely to read;
 *   4. the platform default.
 *
 * THE FIRST TWO WERE THE OTHER WAY ROUND, AND IT WAS WRONG (D-077). The
 * account preference used to win, on the reasoning that an agent who set
 * French on their own machine should not get English at a shared counter. But
 * the session key is only ever written by someone actively using the picker:
 * a fresh session at a shared counter carries nothing, so the account
 * preference still applies there.
 *
 * What the old order actually broke was the ordinary case: a visitor switches
 * the sign-in page to French, signs in, and the service answers in English.
 * An action taken seconds ago outranks a preference saved weeks ago. Found by
 * signing in and looking, not by a test.
 *
 * The session choice applies to this session only; it does not overwrite the
 * account. Someone who wants it kept switches while signed in, which writes it.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $choisie = Locales::orDefault(
            $request->session()->get('locale')
                ?? $request->user()?->locale
                ?? Locales::fromBrowser($request->getLanguages())
        );

        app()->setLocale($choisie);

        // Dates, month names and number formats follow the text. Without this
        // an English page prints "16 septembre 2026".
        setlocale(LC_TIME, $choisie);

        return $next($request);
    }
}
