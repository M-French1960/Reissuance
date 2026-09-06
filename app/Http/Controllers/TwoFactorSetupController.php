<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page de configuration de la double authentification.
 *
 * L'activation, la confirmation et la regeneration des codes de secours sont
 * assurees par les routes de Fortify. Cette page ne fait que presenter l'etat
 * courant et le QR code.
 */
class TwoFactorSetupController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $enabled = $user->two_factor_secret !== null;
        $confirmed = $user->two_factor_confirmed_at !== null;

        return view('auth.two-factor-setup', [
            'enabled' => $enabled,
            'confirmed' => $confirmed,
            'required' => $user->role->requiresTwoFactor(),
            'qrCode' => $enabled && ! $confirmed ? $user->twoFactorQrCodeSvg() : null,
            'recoveryCodes' => $enabled && $confirmed && $request->session()->has('showRecoveryCodes')
                ? $user->recoveryCodes()
                : null,
        ]);
    }
}
