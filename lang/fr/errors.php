<?php

declare(strict_types=1);

return [
    'back_to_account' => 'Retour à mon espace',
    'back_home' => "Retour à l'accueil",

    '403' => [
        'title' => 'Accès refusé',
        'body' => "Votre compte n'a pas accès à cette page. Si vous pensez qu'il s'agit d'une erreur, adressez-vous à l'administration de votre commune.",
    ],
    '404' => [
        'title' => 'Page introuvable',
        'body' => "Cette page n'existe pas, ou elle ne vous est pas accessible. Si vous avez suivi un lien reçu par courriel, il a pu expirer.",
        'note' => 'Vos demandes en cours restent disponibles depuis votre espace.',
    ],
    '419' => [
        'title' => 'Votre session a expiré',
        'body' => "Vous êtes resté trop longtemps sur la page avant de l'envoyer. Par sécurité, les sessions des comptes officiels sont courtes.",
        'note' => 'Reconnectez-vous, puis recommencez. Ce que vous aviez déjà validé est enregistré.',
    ],
    '500' => [
        'title' => 'Le service rencontre une difficulté',
        'body' => "Une erreur nous empêche d'afficher cette page. Elle a été enregistrée, et aucune donnée que vous aviez validée n'est perdue.",
        'note' => "Réessayez dans un moment. Si cela se reproduit, signalez-le à l'administration de votre commune.",
    ],
    '503' => [
        'title' => 'Service momentanément indisponible',
        'body' => 'Le service est en maintenance. Vos demandes en cours ne sont pas affectées, elles vous attendront.',
    ],
];
