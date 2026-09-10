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
        'payment' => env('PHOENIX_PAYMENT_PROVIDER', 'fake'),
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
     * Encaissement.
     *
     * TROIS QUESTIONS SANS REPONSE conditionnent ce bloc
     * (docs/INTEGRATIONS.md 5). Tant qu'elles ne sont pas tranchees, la
     * plateforme n'encaisse RIEN : `gate` vaut « none » et aucun montant n'est
     * code. Le prototype affichait 20 000 CFA — valeur invérifiable, jamais
     * reprise (D-003).
     */
    'payments' => [
        /*
         * OU le paiement est exige. C'est la question 3 d'INTEGRATIONS 5, une
         * decision de service et non d'ingenierie : elle est donc un reglage,
         * pas une architecture.
         *
         *   none              — rien n'est encaisse (defaut)
         *   before_submission — le citoyen paie avant d'envoyer sa demande
         *   before_signature  — le citoyen paie une fois la demande acceptee
         */
        'gate' => env('PHOENIX_PAYMENT_GATE', 'none'),

        /*
         * Montant en UNITE MINEURE de la devise, en entier.
         *
         * Aucune valeur par defaut, a dessein : un tarif de service public se
         * lit dans un texte, il ne se devine pas. Si l'encaissement est active
         * sans montant configure, la plateforme REFUSE de servir plutot que de
         * facturer un chiffre invente (D-039).
         *
         * Le franc CFA n'a pas de subdivision en usage : `minor_unit` vaut 0,
         * et 1 000 F s'ecrit donc 1000.
         */
        'amount_minor' => env('PHOENIX_PAYMENT_AMOUNT_MINOR'),
        'currency' => env('PHOENIX_PAYMENT_CURRENCY', 'XAF'),
        'minor_unit' => (int) env('PHOENIX_PAYMENT_MINOR_UNIT', 0),

        /*
         * Reference reglementaire du tarif, affichee au citoyen.
         * Vide tant que la question 1 d'INTEGRATIONS 5 n'a pas de reponse : on
         * n'affiche pas une base legale qu'on ne peut pas citer (10 du brief).
         */
        'legal_basis' => env('PHOENIX_PAYMENT_LEGAL_BASIS', ''),
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
