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
    /**
     * The providers, and the variable that pins each one.
     *
     * The label is a translation KEY, not a sentence: a constant is resolved
     * once, at compile time, before any language has been chosen.
     */
    public const PROVIDERS = [
        'identity' => ['identity', 'PHOENIX_IDENTITY_PROVIDER'],
        'registry' => ['registry', 'PHOENIX_REGISTRY_PROVIDER'],
        'signature' => ['signature', 'PHOENIX_SIGNATURE_PROVIDER'],
        'payment' => ['payment', 'PHOENIX_PAYMENT_PROVIDER'],
        'facial' => ['facial', 'PHOENIX_FACIAL_PROVIDER'],
    ];

    /**
     * Les sections affichees.
     *
     * @return array<string, list<SystemSetting>>
     */
    public static function sections(): array
    {
        return [
            __('admin.settings.sections.integrations') => self::integrations(),
            __('admin.settings.sections.payments') => self::encaissement(),
            __('admin.settings.sections.security') => self::securite(),
            __('admin.settings.sections.uploads') => self::envois(),
        ];
    }

    /** @return list<SystemSetting> */
    private static function integrations(): array
    {
        $reglages = [];

        foreach (self::PROVIDERS as $cle => [$label, $variable]) {
            $choisi = (string) config("phoenix.providers.{$cle}", 'fake');

            $reglages[] = SystemSetting::ordinaire(
                __('admin.settings.providers.'.$label),
                $choisi,
                $variable,
                $choisi === 'fake' ? __('admin.settings.fake_adapter') : null,
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
                __('admin.settings.labels.payment_moment'),
                match ($porte) {
                    'before_submission' => __('admin.settings.payment_gate.before_submission'),
                    'before_signature' => __('admin.settings.payment_gate.before_signature'),
                    default => __('admin.settings.payment_gate.none'),
                },
                'PHOENIX_PAYMENT_GATE',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.amount'),
                $montant === null || $montant === ''
                    ? null
                    : $montant.' '.config('phoenix.payments.currency'),
                'PHOENIX_PAYMENT_AMOUNT_MINOR',
                $porte !== 'none' && ($montant === null || $montant === '')
                    ? __('admin.settings.warnings.no_amount')
                    : null,
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.legal_basis'),
                config('phoenix.payments.legal_basis'),
                'PHOENIX_PAYMENT_LEGAL_BASIS',
                blank(config('phoenix.payments.legal_basis')) && $porte !== 'none'
                    ? __('admin.settings.warnings.no_legal_basis')
                    : null,
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.hrskills_sandbox'),
                (bool) config('phoenix.payments.hrskills.sandbox'),
                'PHOENIX_HRSKILLS_SANDBOX',
                (bool) config('phoenix.payments.hrskills.sandbox')
                    ? __('admin.settings.warnings.sandbox')
                    : null,
            ),
            SystemSetting::secret(
                __('admin.settings.labels.hrskills_public_key'),
                config('phoenix.payments.hrskills.public_key'),
                'PHOENIX_HRSKILLS_PUBLIC_KEY',
            ),
            SystemSetting::secret(
                __('admin.settings.labels.hrskills_secret_key'),
                config('phoenix.payments.hrskills.secret_key'),
                'PHOENIX_HRSKILLS_SECRET_KEY',
            ),
            SystemSetting::secret(
                __('admin.settings.labels.hrskills_webhook_secret'),
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
                __('admin.settings.labels.blind_index_key'),
                config('phoenix.blind_index_key'),
                'PHOENIX_BLIND_INDEX_KEY',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.official_session_lifetime'),
                __('admin.settings.units.minutes', ['count' => config('phoenix.security.official_session_lifetime')]),
                'PHOENIX_OFFICIAL_SESSION_LIFETIME',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.login_max_attempts'),
                config('phoenix.security.login_max_attempts'),
                'PHOENIX_LOGIN_MAX_ATTEMPTS',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.login_lockout_seconds'),
                __('admin.settings.units.seconds', ['count' => config('phoenix.security.login_lockout_seconds')]),
                'PHOENIX_LOGIN_LOCKOUT_SECONDS',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.check_compromised_passwords'),
                (bool) config('phoenix.security.check_compromised_passwords'),
                'PHOENIX_CHECK_COMPROMISED_PASSWORDS',
                __('admin.settings.warnings.compromised_check'),
            ),
        ];
    }

    /** @return list<SystemSetting> */
    private static function envois(): array
    {
        return [
            SystemSetting::ordinaire(
                __('admin.settings.labels.max_upload_size'),
                __('admin.settings.units.kilobytes', ['count' => round(((int) config('phoenix.uploads.max_bytes')) / 1024)]),
                '',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.compression_target'),
                __('admin.settings.units.kilobytes', ['count' => round(((int) config('phoenix.uploads.target_bytes')) / 1024)]),
                '',
            ),
            SystemSetting::ordinaire(
                __('admin.settings.labels.accepted_formats'),
                implode(', ', (array) config('phoenix.uploads.accepted_mime')),
                '',
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
                $factices[] = __('admin.settings.providers.'.$label);
            }
        }

        return $factices;
    }
}
