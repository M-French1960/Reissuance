<?php

declare(strict_types=1);

return [
    'verify' => [
        'title' => "Vérifier qu'un acte est authentique",
        'lede' => "Pour les administrations, écoles, employeurs et ambassades : confirmez qu'une copie délivrée par ce service est authentique. Aucun compte n'est nécessaire.",
        'code' => 'Code de vérification',
        'code_hint' => 'Imprimé en bas de la copie, sous « Vérifier cette copie ». Lettres et chiffres, quelle que soit la façon de les espacer.',
        'check' => 'Vérifier',
        'genuine_title' => 'Cet acte est authentique',
        'genuine_body' => "Comparez ce qui suit avec le document que vous avez sous les yeux. Si quelque chose diffère, la copie n'est pas celle que ce service a délivrée.",
        'unknown_title' => 'Aucun acte ne porte ce code',
        'unknown_body' => "Vérifiez que le code a été recopié correctement : lettres et chiffres uniquement, et les lettres O et I n'apparaissent jamais dans un code. Si cela ne correspond toujours pas, le document n'a pas été délivré par ce service.",
        'reference' => 'Référence de la demande',
        'centre' => 'Délivré par',
        'issued_on' => 'Délivré le',
        'initials' => 'Titulaire, initiales',
        'birth_year' => 'Année de naissance',
        'privacy_note' => "Seul ce qui est nécessaire à la comparaison est affiché. Un code seul n'apprend à personne à qui appartient un acte.",
        'demo_title' => "Cet acte n'a aucune valeur juridique",
        'demo_body' => "Il a été produit alors que le service fonctionne avec son prestataire de signature de démonstration. Il est authentique au sens où ce service l'a délivré, et il n'est pas un acte juridique.",
        'no_revocation' => "Ce service n'enregistre aucune révocation d'acte : cette réponse dit que l'acte a été délivré, et par qui, non qu'il est toujours valable aujourd'hui.",
        'not_printed' => "Les actes signés avant l'existence de cette vérification ne portent pas de code imprimé. Leur titulaire peut toujours les télécharger depuis son compte.",
    ],
];
