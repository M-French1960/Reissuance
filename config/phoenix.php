<?php

declare(strict_types=1);

return [
    /*
     * Cle HMAC de l'index aveugle sur le numero de piece.
     * Distincte de APP_KEY : sa perte rend la recherche par numero impossible,
     * sans perte de donnees. Voir docs/DATA_MODEL.md 3.
     */
    'blind_index_key' => env('PHOENIX_BLIND_INDEX_KEY', ''),

    /*
     * Adaptateurs d'integration. « fake » est le defaut : aucune API reelle
     * n'est documentee a ce jour (docs/INTEGRATIONS.md).
     */
    'providers' => [
        'identity' => env('PHOENIX_IDENTITY_PROVIDER', 'fake'),
        'registry' => env('PHOENIX_REGISTRY_PROVIDER', 'fake'),
        'signature' => env('PHOENIX_SIGNATURE_PROVIDER', 'fake'),
    ],

    /*
     * Securite.
     */
    'security' => [
        /*
         * Verification du mot de passe contre les fuites connues.
         *
         * ATTENTION : cette verification interroge api.pwnedpasswords.com et
         * **echoue en mode ouvert**. Si le service est injoignable — ce qui
         * est le cas par defaut sur une installation locale hors ligne — tout
         * mot de passe passe. Le plancher reel est App\Rules\NotAWeakPassword.
         */
        'check_compromised_passwords' => env('PHOENIX_CHECK_COMPROMISED_PASSWORDS', true),

        /*
         * Duree de session, en minutes, pour les roles officiels.
         * Le 4.1 impose une duree courte pour ces roles.
         */
        'official_session_lifetime' => (int) env('PHOENIX_OFFICIAL_SESSION_LIFETIME', 30),

        /*
         * Verrouillage progressif : tentatives autorisees avant blocage, et
         * duree de blocage de base en secondes (doublee a chaque palier).
         */
        'login_max_attempts' => (int) env('PHOENIX_LOGIN_MAX_ATTEMPTS', 5),
        'login_lockout_seconds' => (int) env('PHOENIX_LOGIN_LOCKOUT_SECONDS', 60),
    ],

    /*
     * Cible de compression cote navigateur avant envoi (D-008).
     */
    'uploads' => [
        'max_bytes' => 2 * 1024 * 1024,
        'target_bytes' => 250 * 1024,
        'max_dimension' => 1600,
        'accepted_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
