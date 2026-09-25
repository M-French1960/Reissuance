<?php

declare(strict_types=1);

return [
    'citizen' => [
        'title' => 'Mon espace',
        'heading' => 'Mon espace',
        'lede' => "Suivez vos demandes de réédition d'acte de naissance.",
        'profile_incomplete_title' => 'Complétez votre profil',
        'profile_incomplete_body' => 'Votre profil doit être complet avant de déposer une demande.',
        'complete_profile' => 'Compléter mon profil',
        'requests_title' => 'Mes demandes',
        'empty_title' => "Aucune demande pour l'instant",
        'empty_body' => "Déposez votre première demande de réédition d'acte de naissance.",
        'apply' => 'Faire une demande',
        'apply_again' => 'Faire une nouvelle demande',
        'all_requests' => 'Toutes mes demandes',

        'welcome_named' => 'Bonjour, :name',
        'welcome_anonymous' => 'Bienvenue',
        'welcome_lede' => "Suivez vos demandes, lisez ce que l'officier vous écrit et téléchargez votre acte, depuis cet espace.",

        'stat_active' => 'Demandes en cours',
        'stat_delivered' => 'Actes délivrés',
        'stat_see_all' => 'Voir mes demandes',

        'action_title' => 'Faire rééditer un acte de naissance',
        'action_body' => 'Pour un acte perdu ou abîmé.',

        'resume_draft' => 'Terminer ma demande commencée',

        'track_title' => 'Où en est ma demande',
        'track_reference' => 'Numéro',
        'track_centre' => 'Centre',
        'track_submitted' => 'Déposée le',
        'track_open' => 'Ouvrir la demande',
        'track_empty' => "Vous n'avez aucune demande en cours. Lancez-en une et chaque étape s'affichera ici, du dépôt jusqu'à la remise de la copie.",

        'checklist_title' => 'Ce qu\'il faut préparer',
        'checklist_id' => "Une pièce d'identité valide",
        'checklist_photo' => 'Une photo de vous',
        'checklist_details' => "Les informations de l'acte et le centre d'état civil",
        'checklist_email' => 'L\'adresse électronique qui reçoit les notifications',

        'activity_title' => 'Activité récente',
        'activity_empty' => 'Les mises à jour de vos demandes apparaîtront ici.',
        'activity_all' => 'Tout voir',

        'payments_title' => 'Paiements',
        'payments_empty' => "Aucun paiement pour l'instant. Un règlement ne vous est demandé que si cette installation l'exige, et le montant vous est affiché avant.",
        'payments_all' => 'Voir mes demandes',
        'payments_request' => 'Demande',
        'payments_amount' => 'Montant',
        'payments_method' => 'Moyen',
        'payments_date' => 'Date',
        'payments_status' => 'Statut',
    ],

    'officer' => [
        'title' => "Poste de l'officier",
        'heading' => 'Poste de vérification',
        'centre_line' => 'Votre centre est :centre. Vous ne voyez que les demandes de ce centre.',
        'counters_note' => 'Chaque compteur ouvre la file filtrée sur son statut.',
        'queue_title' => 'File de traitement',
        'queue_body' => "Prenez un dossier en charge, menez les cinq contrôles, puis décidez. Tout ce que vous ouvrez est inscrit au journal d'audit.",
        'open_queue' => 'Ouvrir la file',
    ],

    'mayor' => [
        'title' => 'Espace du maire',
        'heading' => 'Signature des actes',
        'commune_line' => 'Votre commune est :commune. Vous ne voyez que les demandes prêtes à signer ou transmises pour arbitrage.',
        'sign_title' => 'Signer un acte',
        'sign_body' => "Vous signez le :strong. Ouvrez-le avant de décider. Chaque signature demande votre code d'authentification, ou votre appareil si vous en avez enrôlé un.",
        'sign_body_strong' => "projet d'acte rédigé par l'officier",
        'open_signing_queue' => 'Ouvrir la file de signature',
        'enrol_device' => 'Enrôler un appareil pour signer',
    ],

    'admin' => [
        'title' => 'Administration',
        'heading' => 'Administration',
        'scope_title' => 'Périmètre de ce compte',
        'scope_body' => "Votre rôle donne accès à la gestion des comptes et aux métadonnées du journal d'audit. Il ne donne accès à :strong dossier de citoyen : ni pièce d'identité, ni photographie, ni numéro de pièce.",
        'scope_strong' => 'aucun',
        'accounts_by_role' => 'Comptes par rôle et statut',
        'accounts_distribution' => 'Répartition des comptes',
        'count' => 'Nombre',
        'manage_accounts' => 'Gérer les comptes',
        'manage_accounts_body' => 'Créer, activer, suspendre ou réaffecter un officier ou un maire.',
        'audit_body' => 'Qui a consulté quel dossier et quand. Le contenu des dossiers reste inaccessible.',
        'view' => 'Consulter',
    ],
];
