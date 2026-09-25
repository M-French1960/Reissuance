<?php

declare(strict_types=1);

/*
 * LA PREMIERE PAGE DU SERVICE (D-072, D-078).
 *
 * Chaque phrase d'ici est une phrase d'administration adressee a un citoyen :
 * chacune doit donc etre vraie de CE logiciel. La maquette livree pour cette
 * page promettait des choses que l'application ne fait pas : notification par
 * SMS, declaration de perte delivree par le commissariat, actes de mariage et
 * de deces « bientot », un suivi public, et deux reponses laissees en « [a
 * completer] ». Rien de tout cela n'a ete repris. Ce qui l'a remplace dit ce
 * que l'application fait, et dit franchement ce qu'elle ne fait pas.
 */
return [
    'title' => 'Accueil',
    'meta_description' => "Demandez en ligne une nouvelle copie de votre acte de naissance. Votre centre d'état civil l'établit, votre maire le signe.",

    /* --- Navigation ---------------------------------------------------- */
    'nav_documents' => 'Documents',
    'nav_steps' => 'Comment ça marche',
    'nav_checklist' => 'Ce qu\'il faut préparer',
    'nav_faq' => 'Questions',
    'menu_open' => 'Ouvrir le menu',
    'menu_close' => 'Fermer le menu',

    /* --- Avertissement de démonstration ---------------------------------- */
    'demo_title' => 'Service de démonstration',
    'demo_body' => "Les actes produits par cette installation portent la mention :mention et ne peuvent être présentés à aucune administration. N'engagez pas de démarche réelle sur cette base.",
    'demo_mention' => 'sans valeur juridique',

    /* --- Héros ------------------------------------------------------------ */
    'hero_title' => "Votre acte de naissance perdu, réédité sans file d'attente",
    'hero_lead' => "Déposez votre demande en ligne, suivez la vérification par l'officier d'état civil et la signature du maire, puis téléchargez votre nouvelle copie.",
    'hero_start' => 'Commencer une demande',
    'hero_steps' => 'Voir les étapes',

    'track_label' => 'Vous avez déjà fait une demande ?',
    'track_body' => "Le suivi d'un dossier n'est pas public, et ne le sera pas. Connectez-vous : vos demandes et leur étape exacte sont dans votre espace.",
    'track_action' => 'Me connecter pour suivre ma demande',

    /* --- La carte illustrée du héros -------------------------------------- */
    'mock_caption' => "Illustration de l'écran que vous verrez. Ce n'est pas un acte, et il n'appartient à personne.",
    'mock_title' => 'Acte de naissance',
    'mock_sub' => 'Demande de réédition, exemple',
    'mock_holder_label' => 'Titulaire',
    'mock_holder_value' => 'Votre nom',
    'mock_center_label' => "Centre d'état civil",
    'mock_center_value' => 'Le centre que vous choisissez',
    'mock_reason_label' => 'Motif',
    'mock_reason_value' => 'Perte',
    'mock_1' => 'Demande reçue',
    'mock_1_note' => 'Pièces déposées',
    'mock_2' => "Vérifiée par l'officier",
    'mock_2_note' => 'Correspondance avec le registre',
    'mock_3' => 'Signature du maire',
    'mock_3_note' => 'En cours',
    'mock_4' => 'Copie disponible',
    'mock_4_note' => 'Téléchargement depuis votre espace',
    'mock_badge' => 'Vous êtes prévenu à chaque étape',

    /* --- Ce qui peut être demandé ----------------------------------------- */
    'docs_title' => 'Quel document peut être réédité ici ?',
    'docs_intro' => "Ce service traite les actes de naissance, et eux seuls. Pour tout autre acte d'état civil, adressez-vous à votre centre d'état civil.",
    'docs_available' => 'Disponible',
    'docs_unavailable' => 'Non traité ici',
    'docs_birth_title' => 'Acte de naissance',
    'docs_birth_body' => "Pour un acte perdu ou abîmé. Vous indiquez le centre d'état civil qui le détient, et l'officier le recherche dans le registre.",
    'docs_birth_cta' => 'Commencer la demande',
    'docs_marriage_title' => 'Acte de mariage',
    'docs_marriage_body' => "Vous ne pouvez pas en demander un ici. Aucune date n'est fixée pour son ajout.",
    'docs_death_title' => 'Acte de décès',
    'docs_death_body' => "Vous ne pouvez pas en demander un ici. Aucune date n'est fixée pour son ajout.",

    /* --- Les quatre étapes -------------------------------------------------- */
    'steps_title' => 'Quatre étapes, chacune traitée par la bonne personne',
    'steps_intro' => "Vous voyez à tout moment où en est votre dossier, et vous pouvez l'annuler tant que l'acte n'est pas signé.",
    'step1_title' => 'Vous déposez la demande',
    'step1_body' => "Informations de l'acte, photo de votre pièce d'identité et photo de vous, depuis votre téléphone ou votre ordinateur.",
    'step1_who' => 'Vous',
    'step2_title' => "L'officier vérifie",
    'step2_body' => "Il recherche l'acte dans le registre du centre, contrôle votre pièce d'identité et compare votre photo. Il peut vous écrire si quelque chose manque.",
    'step2_who' => "Officier d'état civil",
    'step3_title' => 'Le maire signe',
    'step3_body' => "Le maire lit l'acte rédigé par l'officier, puis le signe, ou renvoie le dossier pour correction.",
    'step3_who' => 'Maire',
    'step4_title' => 'Vous récupérez la copie',
    'step4_body' => "Vous recevez un courriel et une notification dans votre espace, puis vous téléchargez l'acte.",
    'step4_who' => 'Vous',

    /* --- Ce qu'il faut préparer ---------------------------------------------- */
    'bring_title' => 'Préparez ces éléments avant de commencer',
    'bring_intro' => 'Avoir tout sous la main permet de terminer la demande en une seule fois.',
    'bring_id_title' => "Une pièce d'identité valide",
    'bring_id_desc' => "Photographiée de façon lisible. Le fichier est rangé hors de l'espace web public, aucune adresse ne permet de le deviner, et chaque ouverture par un agent est enregistrée.",
    'bring_photo_title' => 'Une photo de vous',
    'bring_photo_desc' => "Prise avec votre téléphone, face à l'objectif. Elle est comparée à votre pièce d'identité.",
    'bring_details_title' => "Les informations de l'acte",
    'bring_details_desc' => "Centre d'état civil, date et lieu de naissance, année d'enregistrement, noms de vos parents. Le numéro de l'acte si vous l'avez.",
    'bring_email_title' => 'Une adresse électronique',
    'bring_email_desc' => "C'est elle qui reçoit les notifications. Ce service n'envoie aucune notification par SMS.",
    'bring_note' => "Les photos sont réduites sur votre téléphone avant l'envoi, pour consommer moins de données.",

    'aside_title' => 'Vous pouvez vous arrêter et revenir',
    'aside_body' => "Chacune des quatre étapes est enregistrée dès qu'elle est terminée. Si votre connexion tombe ou si votre batterie s'épuise, vous reprenez la demande là où vous l'aviez laissée.",
    'aside_cta' => 'Créer un compte',

    /* --- Pourquoi faire confiance au service ---------------------------------- */
    'trust_title' => 'Un acte de naissance est un document sensible',
    'trust_intro' => 'La plateforme est conçue en conséquence, et ces trois affirmations se vérifient dans le logiciel lui-même.',
    'trust1_title' => "L'accès est restreint",
    'trust1_body' => "Seuls l'officier du centre que vous avez choisi et le maire de cette commune peuvent ouvrir votre dossier. Personne d'autre sur la plateforme ne le peut.",
    'trust2_title' => 'Chaque acte est signé',
    'trust2_body' => 'La copie délivrée porte la signature du maire et une preuve qui peut être vérifiée après coup.',
    'trust3_title' => 'Rien ne se passe sans trace',
    'trust3_body' => "Chaque action sur votre demande est datée, attribuée et enregistrée. Le journal ne peut être ni modifié ni effacé depuis l'application : la base de données le lui refuse.",

    /* --- Questions ------------------------------------------------------------- */
    'faq_title' => 'Les questions que vous vous posez',
    'faq_intro' => "Une fois votre compte créé, vous pouvez écrire depuis votre dossier à l'officier qui le traite.",

    'faq_q1' => 'Combien de temps prend une réédition ?',
    'faq_a1' => "Aucun délai n'est annoncé, parce qu'il dépend du centre d'état civil et de la recherche dans le registre. Plutôt que d'afficher un chiffre qui ne serait pas tenu, le service vous montre à tout moment, depuis votre espace, l'étape exacte où en est votre demande.",

    'faq_q2' => 'Combien coûte la demande et comment payer ?',
    'faq_a2' => "Le tarif est fixé par l'administration qui exploite cette installation, pas par la plateforme. Si un paiement est exigé, le montant vous est affiché avant que vous ne payiez, et le règlement se fait par Orange Money ou MTN Mobile Money. Rien n'est jamais prélevé sans que vous ayez vu le montant.",

    'faq_q3' => "Mon centre d'état civil est-il couvert ?",
    'faq_a3' => "La liste des centres raccordés s'affiche à la troisième étape du formulaire. Si le vôtre n'y figure pas, la demande ne peut pas encore se faire ici, et la plateforme ne vous laissera pas choisir un autre centre à sa place.",

    'faq_q4' => "Que se passe-t-il s'il manque une pièce ?",
    'faq_a4' => "L'officier vous écrit depuis votre dossier. Vous êtes prévenu, vous lisez et vous répondez au message dans votre espace. Une limite à connaître : une fois la demande envoyée, ses photos ne peuvent plus être remplacées depuis votre espace, prenez donc le temps de vérifier que les vôtres sont lisibles avant d'envoyer.",

    'faq_q5' => "Vous êtes officier d'état civil, maire ou administrateur ?",
    'faq_a5' => "Votre compte est créé par l'administration et ne s'ouvre pas depuis cette page. Connectez-vous avec l'adresse qui vous a été communiquée.",

    /* --- Appel final ------------------------------------------------------------ */
    'final_title' => 'Prêt à faire rééditer votre acte ?',
    'final_body' => 'Créez votre compte, puis suivez les quatre étapes du formulaire.',
    'final_cta' => 'Commencer une demande',

    /* --- Pied de page -------------------------------------------------------- */
    'footer_tag' => "Réédition en ligne des actes d'état civil.",
    'footer_service' => 'Le service',
    'footer_help' => 'Aide',
    'footer_birth' => 'Acte de naissance',
    'footer_track' => 'Suivre une demande',
    'footer_signin' => 'Se connecter',
    'footer_status' => 'État du service',
    'footer_year' => 'PHOENIX, :year',
];
