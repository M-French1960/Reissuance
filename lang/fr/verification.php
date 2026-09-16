<?php

declare(strict_types=1);

return [
    'steps' => [
        1 => 'Informations de la demande',
        2 => "Vérification de la pièce d'identité",
        3 => 'Examen des photographies',
        4 => "Recherche dans le registre d'état civil",
        5 => 'Décision',
    ],
    'step_navigation' => 'Navigation entre les étapes',
    'step_number' => 'Étape :number',

    'heading' => 'Vérification :reference',
    'meta' => 'Demandeur : :name. Statut : :status.',
    'pass_number' => 'Passe n° :number, dossier retourné par le maire',
    'shortcuts' => 'Raccourcis : :next ou n pour l\'étape suivante, :previous ou p pour la précédente.',
    'previous_step' => 'Étape précédente',
    'next_step' => 'Étape suivante',
    'back_to_queue' => 'Retour à la file',

    'claim_title' => 'Dossier à prendre en charge',
    'claim_body' => 'Personne ne traite ce dossier. Prenez-le en charge pour pouvoir le vérifier et décider.',
    'claim_action' => 'Prendre en charge',
    'read_only_title' => 'Lecture seule',
    'read_only_body' => 'Ce dossier est pris en charge par :name. Vous pouvez le consulter, la consultation est journalisée, mais la décision lui revient.',
    'unassigned_title' => 'Dossier sans agent affecté',
    'unassigned_body' => "Ce dossier est à l'état :status et n'est affecté à personne : il ne peut être ni pris en charge ni décidé en l'état. Signalez-le à l'administrateur.",

    'not_recorded' => 'Non renseignée',
    'in_progress' => 'En cours',
    'step_column' => 'Étape',
    'summary_title' => 'Récapitulatif des vérifications',
    'incomplete_title' => 'Vérification incomplète',
    'incomplete_body' => "Vous ne pourrez pas accepter cette demande tant que les quatre vérifications n'ont pas de résultat. Il manque : :steps.",
    'incomplete_note' => 'Le rejet et la transmission au maire restent possibles.',
];
