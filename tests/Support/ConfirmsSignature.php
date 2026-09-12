<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/**
 * Fournit un code de confirmation valide pour un maire.
 *
 * POURQUOI CE TRAIT EXISTE (D-069). Signer un acte exige desormais que le
 * maire ressaisisse son code d'authentification. Six tests qui signaient par
 * HTTP sont tombes le jour ou la regle est entree en vigueur — a juste titre :
 * ils exercaient un chemin qui n'existe plus.
 *
 * Le code est calcule a partir du VRAI secret du compte, avec le meme moteur
 * que la verification. Poser un jeton factice en session aurait contourne
 * exactement ce que ces tests doivent prouver.
 */
trait ConfirmsSignature
{
    /**
     * Dote le compte d'un secret TOTP et rend le secret en clair.
     *
     * La fabrique pose `two_factor_confirmed_at` sans secret ; c'est l'etat
     * qu'on rend coherent ici pour les tests qui en ont besoin.
     */
    protected function equipeLaDoubleAuthentification(User $officiel): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        /*
         * ECRIT EXACTEMENT COMME FORTIFY L'ECRIT, et c'est tout l'interet de
         * ce trait.
         *
         * La base porte DEUX couches de chiffrement : Fortify chiffre le
         * secret, puis le cast `encrypted` du modele chiffre le resultat. Ma
         * premiere version de ce fixture posait le secret EN CLAIR ; les
         * tests passaient, et le code de production n'aurait verifie aucun
         * code reel. Un fixture qui ne reproduit pas l'etat reel ne teste
         * rien.
         */
        $officiel->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    /** Le code affiche a cet instant par l'application d'authentification. */
    protected function codeDeConfirmation(User $officiel): string
    {
        $stocke = $officiel->fresh()->two_factor_secret;

        $secret = $stocke === null
            ? $this->equipeLaDoubleAuthentification($officiel)
            : Fortify::currentEncrypter()->decrypt($stocke);

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /** Des codes de secours, ecrits comme Fortify les ecrit.
     *
     * @param  list<string>  $codes
     */
    protected function poseDesCodesDeSecours(User $officiel, array $codes): void
    {
        $officiel->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($codes)),
        ])->save();
    }
}
