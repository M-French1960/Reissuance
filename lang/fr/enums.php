<?php

declare(strict_types=1);

return [
    'request_status' => [
        'draft' => 'Brouillon, non envoyée',
        'pending' => 'Envoyée, en attente de traitement',
        'under_review' => 'En cours de vérification',
        'awaiting_signature' => 'En attente de signature du maire',
        'escalated' => 'Transmise au maire pour arbitrage',
        'signed' => 'Signée, acte disponible',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée par le demandeur',
    ],

    'user_role' => [
        'citizen' => 'Citoyen',
        'officer' => "Officier d'état civil",
        'mayor' => 'Maire',
        'admin' => 'Administrateur',
    ],

    'decision' => [
        'accepted' => 'Acceptée',
        'rejected' => 'Rejetée',
        'escalated' => 'Transmise au maire',
        'signed' => 'Signée',
        'approved_by_exception' => 'Approuvée par exception',
        'returned' => "Retournée à l'officier",
    ],

    'verification_result' => [
        'match' => 'Correspondance trouvée',
        'no_match' => 'Aucune correspondance',
        'inconclusive' => 'Résultat non concluant',
        'provider_unavailable' => 'Service externe indisponible',
        'not_recorded' => 'Non renseignée',
    ],

    'payment_status' => [
        'pending' => 'En attente de confirmation',
        'authorised' => 'Autorisé, fonds non acquis',
        'settled' => 'Payé',
        'failed' => 'Refusé',
        'expired' => 'Expiré sans réponse',
        'refunded' => 'Remboursé',
    ],

    'payment_operator' => [
        'orange_money' => 'Orange Money',
        'mtn_mobile_money' => 'MTN Mobile Money',
        'orange_money_hint' => 'Le numéro Orange depuis lequel vous réglez.',
        'mtn_mobile_money_hint' => 'Le numéro MTN depuis lequel vous réglez.',
    ],

    'account_status' => [
        'active' => 'Actif',
        'pending' => 'En attente de configuration',
        'suspended' => 'Suspendu',
        'disabled' => 'Désactivé',
    ],
];
