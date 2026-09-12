<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Confirmation d'identite au moment de signer un acte.
 *
 * POURQUOI CETTE CLASSE EXISTE (D-069). Jusqu'ici, signer un acte ne demandait
 * qu'une session ouverte. La session officielle dure 30 minutes : un
 * navigateur de maire laisse ouvert permettait a qui passait derriere lui de
 * delivrer des actes d'etat civil. Le §4.3 du brief exige une decision
 * EXPLICITE du maire ; un clic dans une session deja ouverte n'en est pas une.
 *
 * CE QUE CETTE CONFIRMATION APPORTE, et qu'aucune autre barriere n'apportait :
 * la preuve que le titulaire du compte etait PRESENT au moment precis de la
 * signature, et non seulement qu'il s'etait connecte une demi-heure plus tot.
 *
 * POURQUOI SEULEMENT LA SIGNATURE. Retourner un dossier a l'officier ou
 * rejeter une demande sont des decisions qui laissent une trace et se
 * corrigent. Signer produit un acte d'etat civil. C'est la seule action dont
 * l'usurpation coute un acte frauduleux — l'exigence n°1 du brief.
 */
final class SignatureConfirmation
{
    /** Methodes de confirmation, telles qu'enregistrees sur la signature. */
    public const METHOD_TOTP = 'totp';

    public const METHOD_RECOVERY = 'recovery_code';

    /**
     * Signature par appareil enrole (WebAuthn, D-070).
     *
     * Enregistree suffixee de l'identifiant de l'appareil — « device:12 » —
     * parce qu'en cas de contestation, savoir QUEL appareil a signe compte
     * autant que savoir qu'un appareil a signe.
     */
    public const METHOD_DEVICE = 'device';

    /**
     * Tentatives permises avant blocage.
     *
     * Un code a six chiffres se devine en un million d'essais ; sans limite,
     * ce n'est pas une barriere. Cinq essais par quart d'heure laissent la
     * place a une faute de frappe et ferment l'essai systematique.
     */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $totp,
    ) {}

    /**
     * Verifie le code, et rend la methode retenue.
     *
     * Leve si le code est faux, si le compte n'a pas de 2FA confirmee, ou si
     * le nombre d'essais est depasse. L'appelant doit l'invoquer AVANT de
     * produire quoi que ce soit : un document construit puis jete resterait un
     * document construit sans decision du maire.
     *
     * @throws DomainException
     */
    public function confirm(User $mayor, ?string $code, ?string $ip = null): string
    {
        $cle = $this->throttleKey($mayor);

        if (RateLimiter::tooManyAttempts($cle, self::MAX_ATTEMPTS)) {
            $this->audit($mayor, 'act.signature_confirmation_blocked', $ip);

            $secondes = RateLimiter::availableIn($cle);

            throw new DomainException(
                'Trop de codes erronés. La signature est bloquée pendant '
                .ceil($secondes / 60).' minute(s). '
                ."Si vous n'êtes pas à l'origine de ces tentatives, prévenez l'administrateur."
            );
        }

        $code = trim((string) $code);

        if ($code === '') {
            throw new DomainException(
                'Entrez le code de votre application d’authentification pour signer.'
            );
        }

        if ($mayor->two_factor_secret === null || $mayor->two_factor_confirmed_at === null) {
            // Ne devrait pas arriver : la contrainte `users_official_2fa_check`
            // refuse en base un compte officiel actif sans 2FA confirmee. On
            // le verifie tout de meme plutot que de laisser passer.
            throw new DomainException(
                "Votre double authentification n'est pas configurée : la signature est "
                .'impossible. Configurez-la depuis la page Sécurité.'
            );
        }

        $methode = $this->matches($mayor, $code);

        if ($methode === null) {
            RateLimiter::hit($cle, self::DECAY_SECONDS);

            // UN ECHEC EST UN SIGNAL, pas un incident anodin : quelqu'un a
            // tente de signer un acte sans savoir le code. Il entre au journal.
            $this->audit($mayor, 'act.signature_confirmation_failed', $ip);

            throw new DomainException(
                'Code incorrect. Vérifiez le code affiché par votre application '
                ."d'authentification, ou utilisez un code de secours."
            );
        }

        RateLimiter::clear($cle);

        return $methode;
    }

    /**
     * Le code correspond-il, et par quel moyen.
     *
     * L'ordre compte peu, mais le TOTP est essaye en premier : c'est le cas
     * courant, et un code de secours consomme ne se recupere pas.
     */
    private function matches(User $mayor, string $code): ?string
    {
        // `verify` de Fortify porte deja la protection contre le rejeu : il
        // garde en cache les codes consommes et refuse un code deja servi.
        // On ne la reecrit pas.
        //
        // LE DECHIFFREMENT EST DOUBLE, ET C'EST VOULU — ou du moins c'est
        // l'etat des lieux. Fortify chiffre lui-meme le secret avant de
        // l'ecrire, et le modele declare en plus `two_factor_secret` en cast
        // `encrypted` : la base porte donc deux couches. L'accesseur en retire
        // une, `Fortify::currentEncrypter()->decrypt` retire l'autre. Tout le
        // code de Fortify procede ainsi — connexion, QR code, confirmation —
        // et s'en ecarter ici ferait echouer toute signature reelle.
        //
        // Je l'ai appris a mes depens : ma premiere version lisait
        // l'accesseur tel quel et passait les tests, parce que MON FIXTURE
        // ecrivait le secret en clair. Le fixture pose desormais le secret
        // exactement comme Fortify.
        if ($this->totp->verify(Fortify::currentEncrypter()->decrypt($mayor->two_factor_secret), $code)) {
            return self::METHOD_TOTP;
        }

        if ($mayor->two_factor_recovery_codes === null) {
            return null;
        }

        foreach ($mayor->recoveryCodes() as $secours) {
            if (hash_equals((string) $secours, $code)) {
                // Consomme : un code de secours ne sert qu'une fois.
                $mayor->replaceRecoveryCode($code);

                return self::METHOD_RECOVERY;
            }
        }

        return null;
    }

    private function throttleKey(User $mayor): string
    {
        return 'phoenix:signature-confirmation:'.$mayor->id;
    }

    private function audit(User $mayor, string $action, ?string $ip): void
    {
        AuditLog::create([
            'actor_id' => $mayor->id,
            'actor_role' => $mayor->role->value,
            'action' => $action,
            'auditable_type' => 'user',
            'auditable_id' => $mayor->id,
            'ip_address' => $ip,
        ]);
    }
}
