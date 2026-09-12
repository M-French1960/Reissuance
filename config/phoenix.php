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
        'facial' => env('PHOENIX_FACIAL_PROVIDER', 'fake'),
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

        /*
         * Domaine des appareils de signature (WebAuthn, D-070).
         *
         * Vide : deduit de APP_URL. A renseigner si l'application est servie
         * sous plusieurs noms — une cle enrolee sous un domaine ne vaut pas
         * sous un autre, c'est precisement ce qui rend WebAuthn resistant a
         * l'hameconnage.
         *
         * RAPPEL : WebAuthn exige un contexte securise. Sans TLS — et hors
         * `localhost` — la signature par appareil est indisponible, et le code
         * d'authentification reste le seul moyen de signer.
         */
        'webauthn_rp_id' => env('PHOENIX_WEBAUTHN_RP_ID', ''),
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

        /*
         * HR-Skills Pay — agregateur mobile money.
         *
         * Contrat releve dans la documentation publiee par le prestataire
         * (hrskills-pay.com). AUCUN APPEL N'A ETE FAIT contre le service
         * reel : sans identifiants, l'adaptateur est verifie contre un
         * serveur simule. Voir D-050.
         */
        'hrskills' => [
            'base_url' => env('PHOENIX_HRSKILLS_BASE_URL', 'https://api.hrskills-pay.com'),
            // Le bac a sable prefixe les chemins par /sandbox.
            'sandbox' => (bool) env('PHOENIX_HRSKILLS_SANDBOX', true),
            // Cle A (publique) et cle B (secrete). Jamais dans le depot.
            'public_key' => env('PHOENIX_HRSKILLS_PUBLIC_KEY', ''),
            'secret_key' => env('PHOENIX_HRSKILLS_SECRET_KEY', ''),
            // Secret de signature des rappels (X-Hub-Signature).
            'webhook_secret' => env('PHOENIX_HRSKILLS_WEBHOOK_SECRET', ''),
            'timeout' => (int) env('PHOENIX_HRSKILLS_TIMEOUT', 20),
        ],
    ],

    /*
     * Signature electronique.
     *
     * L'adaptateur reste « fake » par defaut, et le restera tant que la
     * question A1 de docs/COMPLIANCE_OPEN_QUESTIONS.md n'aura pas de reponse :
     * on ignore aujourd'hui si un acte d'etat civil camerounais signe
     * electroniquement fait foi.
     *
     * Ce bloc configure le CLIENT Docusign, qui lui est construit et verifie
     * contre un serveur simule (D-066). Le renseigner ne suffit pas a activer
     * la signature : voir DocusignSignatureProvider.
     */
    'signature' => [
        'docusign' => [
            /*
             * Serveur d'autorisation, hote nu.
             *   account-d.docusign.com — bac a sable (defaut)
             *   account.docusign.com   — production
             * Cette valeur est aussi le `aud` de l'assertion JWT.
             */
            'oauth_base_path' => env('PHOENIX_DOCUSIGN_OAUTH_BASE', 'account-d.docusign.com'),

            // Cle d'integration de l'application (le `iss` de l'assertion).
            'integration_key' => env('PHOENIX_DOCUSIGN_INTEGRATION_KEY', ''),

            /*
             * Utilisateur impersonne (le `sub`). Le flux JWT Grant agit EN SON
             * NOM : c'est lui, et non l'application, qui apparait comme
             * expediteur de l'enveloppe. Qui doit-il etre — la commune, le
             * maire nominativement ? Question ouverte (D-066).
             */
            'user_id' => env('PHOENIX_DOCUSIGN_USER_ID', ''),

            /*
             * Cle privee RSA : chemin d'un fichier HORS du depot, ou le PEM
             * lui-meme. Le client REFUSE une cle rangee dans l'arborescence du
             * projet — c'est la cle qui autorise a signer des actes.
             */
            'private_key' => env('PHOENIX_DOCUSIGN_PRIVATE_KEY', ''),

            /*
             * Compte Docusign. Vide : le compte par defaut de l'utilisateur.
             * A renseigner des que l'utilisateur appartient a plusieurs
             * comptes — le compte signataire d'un acte ne se devine pas.
             */
            'account_id' => env('PHOENIX_DOCUSIGN_ACCOUNT_ID', ''),

            /*
             * Sceau electronique (`seal_name`, un identifiant rendu par
             * GET /v2.1/accounts/{id}/seals). Le sceau est la seule facon
             * d'apposer une signature sans intervention humaine dans
             * l'interface de Docusign. Il se provisionne sur le compte, il ne
             * se cree pas par l'API.
             */
            'seal_name' => env('PHOENIX_DOCUSIGN_SEAL_NAME', ''),

            'timeout' => (int) env('PHOENIX_DOCUSIGN_TIMEOUT', 30),
        ],
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
