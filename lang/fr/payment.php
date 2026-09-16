<?php

declare(strict_types=1);

return [
    'title' => 'Règlement des frais',
    'request_line' => 'Demande :reference',
    'failed_title' => "Le règlement n'a pas abouti",

    'amount_title' => 'Montant à régler',
    'amount' => 'Montant',
    'legal_basis' => 'Base réglementaire',
    'legal_basis_missing' => "La référence du texte fixant ce tarif n'est pas encore renseignée dans le service.",
    'after_payment_before_send' => "Votre demande sera transmise au centre d'état civil dès que le règlement sera confirmé.",
    'after_payment_before_sign' => 'Votre acte sera signé par le maire dès que le règlement sera confirmé.',

    'confirmed_title' => 'Règlement confirmé',
    'date' => 'Date',
    'method' => 'Moyen',
    'download_receipt' => 'Télécharger le reçu',

    'in_progress_title' => 'Règlement en cours',
    'not_settled_title' => 'Les fonds ne sont pas encore acquis',
    'not_settled_body' => "L'opérateur a bien pris votre ordre, mais le règlement n'est pas terminé. Validez-le sur votre téléphone si ce n'est pas fait, puis actualisez ci-dessous.",
    'refresh' => "Actualiser l'état du règlement",

    'pay_title' => 'Payer',
    'how_to_pay' => 'Comment souhaitez-vous régler ?',
    'payer_number' => 'Numéro de règlement',
    'payer_number_hint' => 'Le numéro de téléphone depuis lequel vous payez, par exemple +237 6 XX XX XX XX.',
    'pay_amount' => 'Régler :amount',
    'back_to_request' => 'Revenir à ma demande',

    'no_fee' => "Aucun frais n'est demandé pour cette démarche.",
    'status_line' => 'État du règlement : :status.',
    'no_payment_running' => "Aucun règlement n'est en cours.",
    'choose_method' => 'Choisissez comment vous souhaitez régler.',
    'give_number' => 'Indiquez le numéro depuis lequel vous réglez.',
];
