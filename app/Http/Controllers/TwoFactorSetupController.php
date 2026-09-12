<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SigningDevice;
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
            /*
             * Les appareils de signature (D-070). Un citoyen n'en enrole pas :
             * il n'a rien a signer. La liste reste vide pour lui, et la carte
             * ne s'affiche pas.
             */
            'peutEnrolerUnAppareil' => $user->role->requiresTwoFactor(),
            'appareils' => SigningDevice::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->get(),
        ]);
    }
}
