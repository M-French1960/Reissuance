<?php

declare(strict_types=1);

return [
    'mayor' => [
        'reason_required_exception' => "Approuver par exception une demande transmise exige un motif : il figurera au dossier et au journal d'audit.",
        'reason_min' => 'Le motif doit être suffisamment explicite : au moins 10 caractères.',
        'cannot_sign_incomplete' => 'Cette demande ne peut pas être signée : la vérification est incomplète. Il manque : :steps.',
        'cannot_sign_unpaid' => 'Cette demande ne peut pas être signée : les frais ne sont pas acquittés. Le demandeur doit régler avant la signature.',
        'issued' => 'Acte délivré pour la demande :reference.',
        'demo_warning' => 'Attention : le document produit porte la mention « sans valeur juridique ».',
        'reject_reason_required' => 'Un motif de rejet est obligatoire : il sera communiqué au citoyen.',
        'rejected' => 'Demande :reference rejetée.',
        'return_reason_required' => "Indiquez à l'officier ce qui doit être repris : ce motif est sa seule consigne.",
        'returned' => "Demande :reference retournée à l'officier pour une nouvelle vérification.",
    ],

    'officer' => [
        'decision_required' => 'Choisissez une décision.',
        'reason_required' => 'Un motif est obligatoire pour rejeter une demande ou la transmettre au maire. Il sera visible dans le dossier.',
        'reason_required_reservation' => "Une vérification n'a pas abouti à une correspondance. Quelle que soit votre décision, un motif est obligatoire : il figurera au dossier et sera lu par le maire.",
        'reason_min' => 'Le motif doit être suffisamment explicite : au moins 10 caractères.',
        'cannot_accept_incomplete' => "Vous ne pouvez pas accepter cette demande tant que les quatre vérifications n'ont pas de résultat. Il manque : :steps.",
        'decision_recorded' => 'Demande :reference : :decision.',
        'next_open' => 'Ouvrir :reference',
        'next_waiting' => 'Une demande suivante est en attente de prise en charge.',
        'result_required' => 'Indiquez le résultat de votre contrôle avant de continuer.',
        'run_facial_first' => 'Lancez la comparaison faciale avant de conclure sur les photographies.',
    ],

    'citizen' => [
        'complete_profile_first' => 'Complétez votre profil avant de déposer une demande.',
        'missing_attachment' => 'Votre demande ne peut pas être envoyée sans :label.',
        'registration_year_min' => "L'année d'enregistrement doit comporter quatre chiffres, par exemple 1990.",
        'registration_year_max' => "L'année d'enregistrement ne peut pas être dans le futur.",
        'birth_date_before' => "La date de naissance doit être antérieure à aujourd'hui.",
        'centre_required' => "Choisissez le centre d'état civil où l'acte a été enregistré.",
        'photo_saved' => 'La photo a été enregistrée.',
        'file_mimetypes' => 'Le fichier doit être une image JPEG, PNG ou WebP.',
        'file_max' => 'Le fichier est trop volumineux. Reprenez la photo : elle sera compressée automatiquement.',
        'file_required' => "Aucun fichier n'a été reçu. Reprenez la photo puis réessayez.",
        'selfie_label' => 'votre photo',
        'id_label' => "votre pièce d'identité",
    ],

    'payment' => [
        'operator_required' => 'Choisissez comment vous souhaitez régler.',
        'payer_reference_required' => 'Indiquez le numéro depuis lequel vous réglez.',
        'none_running' => "Aucun règlement n'est en cours.",
    ],

    'messages' => [
        'body_required' => "Écrivez votre message avant de l'envoyer.",
        'body_max' => 'Votre message est trop long : 2000 caractères au maximum.',
    ],

    'notifications' => [
        'all_read' => 'Toutes vos notifications sont marquées comme lues.',
    ],

    'admin' => [
        'role_reason' => 'Rôle : :role',
        'status_reason_min' => "Indiquez le motif de ce changement : il figurera au journal d'audit.",
        'cannot_activate_without_2fa' => "Ce compte ne peut pas être activé tant que sa double authentification n'est pas configurée par son titulaire.",
        'assignment_released' => 'Affectation libérée : :name ne peut plus traiter ce dossier.',
    ],

    'signature' => [
        'blocked' => "Trop de codes erronés. La signature est bloquée pendant :minutes minutes. Si vous n'êtes pas à l'origine de ces tentatives, prévenez l'administrateur.",
        'code_required' => "Entrez le code de votre application d'authentification pour signer.",
        'two_factor_missing' => "Votre double authentification n'est pas configurée : la signature est indisponible. Configurez-la depuis la page Sécurité.",
        'code_incorrect' => "Code incorrect. Vérifiez le code affiché par votre application d'authentification, ou utilisez l'un de vos codes de secours.",
    ],

    'password' => [
        'too_common' => 'Ce mot de passe contient un terme trop courant. Choisissez une suite de mots sans rapport avec le service.',
        'too_simple' => 'Ce mot de passe suit une suite trop simple. Variez les caractères.',
    ],

    'device' => [
        'label_required' => 'Donnez un nom à cet appareil : vous devrez pouvoir le reconnaître pour le révoquer.',
        'enrolled' => "L'appareil « :label » peut désormais signer.",
        'revoked' => "L'appareil « :label » ne peut plus signer.",
        'domain_missing' => "Le domaine des appareils de signature n'est pas configuré. Renseignez APP_URL, ou PHOENIX_WEBAUTHN_RP_ID.",
        'domain_mismatch' => "La signature par appareil est configurée pour le domaine « :declared », et cette page est servie depuis « :actual ». L'enrôlement et la signature seront refusés par le navigateur. Renseignez PHOENIX_WEBAUTHN_RP_ID avec le domaine réellement utilisé.",
        'not_enrolment' => "La réponse de l'appareil n'est pas une réponse d'enrôlement.",
        'enrolment_failed' => "L'appareil n'a pas pu être enrôlé : :reason",
        'already_enrolled' => 'Cet appareil est déjà enrôlé.',
        'none_enrolled' => "Aucun appareil n'est enrôlé sur votre compte. Utilisez votre code d'authentification.",
    ],
];
