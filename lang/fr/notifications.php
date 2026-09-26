<?php

declare(strict_types=1);

return [
    'unread_count' => '{1} non lue|[2,*] non lues',
    'title' => 'Notifications',
    'detail_note' => "Le détail d'une demande, motif d'un refus compris, se lit sur la page de la demande. Ces messages ne le reprennent pas.",
    'mark_all_read' => 'Marquer la :count non lue comme lue|Marquer les :count non lues comme lues',
    'all_marked_read' => 'Toutes vos notifications sont marquées comme lues.',
    'unread_badge' => 'Non lue',

    'mail' => [
        'subject' => 'Demande :reference : :title',
        'greeting' => 'Bonjour,',
        'action' => 'Voir ma demande',
        'automatic' => "Ce message est automatique. N'y répondez pas.",
        'salutation' => "Le service de réédition d'actes d'état civil",
    ],

    'titles' => [
        'draft' => 'Demande en brouillon',
        'pending' => 'Demande reçue',
        'under_review' => 'Dossier pris en charge',
        'returned' => 'Dossier renvoyé à la vérification',
        'awaiting_signature' => 'Transmis au maire',
        'escalated' => 'Transmis au maire pour examen',
        'signed' => 'Votre acte est disponible',
        'rejected' => 'Demande refusée',
        'cancelled' => 'Demande annulée',
        'complement_requested' => 'Une pièce est nécessaire',
        'complement_provided' => 'Le demandeur a répondu',
    ],

    'bodies' => [
        'draft' => 'Votre demande :reference est un brouillon.',
        'pending' => "Votre demande :reference a bien été transmise au centre d'état civil. Vous serez prévenu à chaque étape.",
        'under_review' => "Un officier d'état civil a pris en charge votre demande :reference et procède aux vérifications.",
        'returned' => "Votre demande :reference a été renvoyée à l'officier d'état civil pour un complément de vérification.",
        'awaiting_signature' => "Votre demande :reference a été acceptée par l'officier d'état civil et attend la signature du maire.",
        'escalated' => 'Votre demande :reference a été transmise au maire pour un examen particulier.',
        'signed' => 'Votre acte est prêt pour la demande :reference. Connectez-vous pour le télécharger.',
        'rejected' => 'Votre demande :reference a été refusée. Le motif figure sur la page de votre demande.',
        'cancelled' => 'Votre demande :reference a été annulée. Vous pouvez en déposer une nouvelle à tout moment.',
        'complement_requested' => "Pour la demande :reference, l'officier a besoin d'une pièce plus lisible. Connectez-vous pour l'envoyer ; le dossier vous attend.",
        'complement_provided' => "Pour la demande :reference, le demandeur a envoyé la pièce que vous avez réclamée. Les contrôles d'identité repartent du début.",
    ],

    'none_title' => 'Aucune notification',
    'none_citizen' => 'Vous serez prévenu ici à chaque étape de vos demandes.',
    'none_officer' => 'Vous serez prévenu ici lorsque le maire vous retournera un dossier. Les demandes à traiter se suivent depuis la file de traitement.',
    'none_other' => 'Rien ne vous a encore été signalé ici.',
];
