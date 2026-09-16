<?php

declare(strict_types=1);

return [
    'requests_title' => 'Mes demandes',
    'requests_empty_title' => "Aucune demande pour l'instant",
    'requests_empty_body' => "Vous n'avez pas encore déposé de demande de réédition d'acte de naissance.",

    'profile' => [
        'title' => 'Mon profil',
        'intro' => "Ces informations vous identifient auprès du centre d'état civil. Elles sont reprises automatiquement dans vos demandes.",
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'birth_date' => 'Date de naissance',
        'birth_place' => 'Lieu de naissance',
        'national_id' => "Numéro de pièce d'identité",
        'national_id_hint' => "Facultatif. Ce numéro est chiffré : il n'est lisible ni dans la base, ni par l'administration du service.",
        'phone' => 'Téléphone',
        'phone_hint' => 'Par exemple +237 6 XX XX XX XX.',
        'address' => 'Adresse',
        'saved' => 'Votre profil a été enregistré.',
    ],

    'tracking' => [
        'title' => 'Suivi de ma demande',
        'heading' => 'Demande :reference',
        'issued_by' => 'Établi par :centre',
        'timeline_title' => 'Où en est ma demande',
        'will_be_told' => "Vous serez averti à chaque changement d'étape.",
        'rejected_title' => 'Pourquoi cette demande a été refusée',

        'milestone_submitted' => 'Demande envoyée',
        'milestone_submitted_detail' => 'Transmise à :centre.',
        'milestone_submitted_detail_generic' => "Transmise au centre d'état civil.",
        'milestone_checked' => "Vérification par l'officier",
        'milestone_checked_detail' => "Contrôle de votre identité et recherche de l'acte d'origine.",
        'milestone_mayor' => 'Décision du maire',
        'milestone_mayor_detail' => 'Signature de votre acte réédité.',
        'milestone_available' => 'Acte disponible',
        'milestone_available_ready' => 'Votre acte est prêt. Téléchargez-le ci-dessous.',
        'milestone_available_pending' => 'Vous pourrez télécharger votre acte.',

        'state_done' => 'terminé',
        'state_current' => 'en cours',
        'state_stopped' => 'non atteint',
        'state_upcoming' => 'à venir',

        'my_certificate' => 'Mon acte',
        'signed_on' => 'Votre acte a été signé le :date par :mayor.',
        'demo_title' => 'Document de démonstration',
        'demo_body' => "Ce document porte la mention « sans valeur juridique » et ne peut être présenté à aucune administration. La plateforme fonctionne avec un prestataire de signature factice.",
        'download' => 'Télécharger mon acte',
        'signature_proof' => 'Preuve de signature',

        'my_documents' => 'Mes pièces',
        'no_documents' => 'Aucune pièce enregistrée.',
        'your_photo' => 'Votre photo',
        'your_id' => "Votre pièce d'identité",
        'size_kb' => ':size Ko',

        'cancel_title' => 'Annuler ma demande',
        'cancel_body' => "Vous pouvez retirer cette demande tant qu'aucun agent ne l'a prise en charge. :strong pour reprendre la démarche, il faudra déposer une nouvelle demande.",
        'cancel_strong' => 'Cette action est définitive :',
        'cancel_reason' => 'Motif',
        'cancel_reason_hint' => "Facultatif. Il aide le centre d'état civil à comprendre.",
        'cancel_action' => 'Annuler ma demande',
        'cancelled' => 'Votre demande :reference a été annulée.',
        'cancelled_paid' => "Des frais ont été réglés pour cette demande. Rapprochez-vous de votre centre d'état civil, la suite dépend des règles de remboursement en vigueur.",

        'back_to_requests' => 'Retour à mes demandes',
    ],
];
