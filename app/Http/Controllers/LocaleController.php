<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switching language.
 *
 * A POST, not a link: this changes state on the server, and a crawler or a
 * prefetch must not be able to change someone's language by following a URL.
 *
 * The choice is written to the account when there is one, so it survives the
 * session and reaches the queued jobs that send notification e-mails. For a
 * visitor it lives in the session, which is all there is to write to.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))],
        ]);

        $request->session()->put('locale', $valide['locale']);
        $request->user()?->forceFill(['locale' => $valide['locale']])->save();

        // Back where they were: switching language is not a navigation step,
        // and losing the page would cost more than the switch is worth.
        return back();
    }
}
