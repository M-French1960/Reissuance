<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * La configuration du systeme, telle que l'administration peut la consulter.
 *
 * POURQUOI CET ECRAN EXISTE, ET POURQUOI IL EST EN LECTURE SEULE. C'est le
 * dernier cas d'utilisation du diagramme, « Manage system settings ». Le
 * rendre modifiable depuis l'interface ouvrirait un vecteur de fraude
 * direct : un administrateur pourrait basculer un prestataire sur
 * l'adaptateur factice et faire delivrer des actes SANS verification reelle,
 * ou changer le tarif d'un service public depuis un navigateur. Ces reglages
 * restent donc des variables d'environnement, sous le controle de qui
 * deploie — et cet ecran dit lesquelles, ou les poser, et ce qu'elles valent.
 *
 * CE QU'IL APPORTE MALGRE TOUT, et ce n'est pas rien : aujourd'hui, personne
 * ne peut voir depuis l'application qu'elle tourne sur des adaptateurs
 * FACTICES. Une demonstration prise pour un service reel est le risque
 * d'exploitation le plus concret de ce projet a ce stade. Cet ecran le dit,
 * en haut, et en rouge.
 *
 * Voir D-060.
 */
final class SystemSettings
{
    /** Les prestataires, et ce qu'ils gardent. */
    public const PROVIDERS = [
        'identity' => ["Vérification d'identité (DGSN)", 'PHOENIX_IDENTITY_PROVIDER'],
        'registry' => ["Registre national d'état civil", 'PHOENIX_REGISTRY_PROVIDER'],
        'signature' => ['Signature électronique', 'PHOENIX_SIGNATURE_PROVIDER'],
        'payment' => ['Encaissement', 'PHOENIX_PAYMENT_PROVIDER'],
        'facial' => ['Reconnaissance faciale', 'PHOENIX_FACIAL_PROVIDER'],
    ];

    /**
     * Les sections affichees.
     *
     * @return array<string, list<SystemSetting>>
     */
    public static function sections(): array
    {
        return [
            'Intégrations' => self::integrations(),
            'Encaissement' => self::encaissement(),
            'Sécurité' => self::securite(),
            'Envoi de pièces' => self::envois(),
        ];
    }

    /** @return list<SystemSetting> */
    private static function integrations(): array
    {
        $reglages = [];

        foreach (self::PROVIDERS as $cle => [$label, $variable]) {
            $choisi = (string) config("phoenix.providers.{$cle}", 'fake');

            $reglages[] = SystemSetting::ordinaire(
                $label,
                $choisi,
                $variable,
                $choisi === 'fake'
                    ? 'Adaptateur FACTICE : aucune vérification réelle n’est effectuée.'
                    : null,
            );
        }

        return $reglages;
    }

    /** @return list<SystemSetting> */
    private static function encaissement(): array
    {
        $porte = (string) config('phoenix.payments.gate', 'none');
        $montant = config('phoenix.payments.amount_minor');

        return [
            SystemSetting::ordinaire(
                'Moment du paiement',
                match ($porte) {
                    'before_submission' => "avant l'envoi de la demande",
                    'before_signature' => 'après acceptation par l’officier',
                    default => 'aucun encaissement',
                },
                'PHOENIX_PAYMENT_GATE',
            ),
            SystemSetting::ordinaire(
                'Montant',
                $montant === null || $montant === ''
                    ? null
                    : $montant.' '.config('phoenix.payments.currency'),
                'PHOENIX_PAYMENT_AMOUNT_MINOR',
                $porte !== 'none' && ($montant === null || $montant === '')
                    ? "L'encaissement est activé sans tarif : la plateforme refusera de servir."
                    : null,
            ),
            SystemSetting::ordinaire(
                'Base légale du tarif',
                config('phoenix.payments.legal_basis'),
                'PHOENIX_PAYMENT_LEGAL_BASIS',
                blank(config('phoenix.payments.legal_basis')) && $porte !== 'none'
                    ? 'Aucune base légale citable : question 1 de docs/INTEGRATIONS.md §5.'
                    : null,
            ),
            SystemSetting::ordinaire(
                'HR-Skills Pay — bac à sable',
                (bool) config('phoenix.payments.hrskills.sandbox'),
                'PHOENIX_HRSKILLS_SANDBOX',
                (bool) config('phoenix.payments.hrskills.sandbox')
                    ? 'Les paiements ne sont pas réels.'
                    : null,
            ),
            SystemSetting::secret(
                'HR-Skills Pay — clé publique',
                config('phoenix.payments.hrskills.public_key'),
                'PHOENIX_HRSKILLS_PUBLIC_KEY',
            ),
            SystemSetting::secret(
                'HR-Skills Pay — clé secrète',
                config('phoenix.payments.hrskills.secret_key'),
                'PHOENIX_HRSKILLS_SECRET_KEY',
            ),
            SystemSetting::secret(
                'HR-Skills Pay — secret de signature des rappels',
                config('phoenix.payments.hrskills.webhook_secret'),
                'PHOENIX_HRSKILLS_WEBHOOK_SECRET',
            ),
        ];
    }

    /** @return list<SystemSetting> */
    private static function securite(): array
    {
        return [
            SystemSetting::secret(
                "Clé de l'index aveugle",
                config('phoenix.blind_index_key'),
                'PHOENIX_BLIND_INDEX_KEY',
            ),
            SystemSetting::ordinaire(
                'Durée de session des rôles officiels',
                config('phoenix.security.official_session_lifetime').' minutes',
                'PHOENIX_OFFICIAL_SESSION_LIFETIME',
            ),
            SystemSetting::ordinaire(
                'Tentatives de connexion avant blocage',
                config('phoenix.security.login_max_attempts'),
                'PHOENIX_LOGIN_MAX_ATTEMPTS',
            ),
            SystemSetting::ordinaire(
                'Durée de blocage de base',
                config('phoenix.security.login_lockout_seconds').' secondes',
                'PHOENIX_LOGIN_LOCKOUT_SECONDS',
            ),
            SystemSetting::ordinaire(
                'Vérification des mots de passe compromis',
                (bool) config('phoenix.security.check_compromised_passwords'),
                'PHOENIX_CHECK_COMPROMISED_PASSWORDS',
                'Cette vérification interroge un service distant et LAISSE PASSER s’il est injoignable (D-015).',
            ),
        ];
    }

    /** @return list<SystemSetting> */
    private static function envois(): array
    {
        return [
            SystemSetting::ordinaire(
                'Taille maximale acceptée',
                round(((int) config('phoenix.uploads.max_bytes')) / 1024).' Ko',
                '—',
            ),
            SystemSetting::ordinaire(
                'Cible après compression',
                round(((int) config('phoenix.uploads.target_bytes')) / 1024).' Ko',
                '—',
            ),
            SystemSetting::ordinaire(
                'Formats acceptés',
                implode(', ', (array) config('phoenix.uploads.accepted_mime')),
                '—',
            ),
        ];
    }

    /**
     * Les prestataires encore branches sur un adaptateur factice.
     *
     * @return list<string>
     */
    public static function fakeProviders(): array
    {
        $factices = [];

        foreach (self::PROVIDERS as $cle => [$label, $_]) {
            if ((string) config("phoenix.providers.{$cle}", 'fake') === 'fake') {
                $factices[] = $label;
            }
        }

        return $factices;
    }
}
