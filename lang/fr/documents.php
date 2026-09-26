<?php

declare(strict_types=1);

return [
    'demo_notice' => 'DOCUMENT DE DEMONSTRATION - SANS VALEUR JURIDIQUE',
    'draft_notice' => "PROJET D'ACTE - NON SIGNE - SANS VALEUR",

    'receipt_demo_notice' => "RECU DE DEMONSTRATION - AUCUNE SOMME N'A ETE ENCAISSEE",
    'receipt_fields' => [
        'request_reference' => 'Référence de la demande',
        'applicant' => 'Demandeur',
        'amount_paid' => 'Montant réglé',
        'payment_date' => 'Date du règlement',
        'payment_method' => 'Moyen de paiement',
        'transaction_reference' => 'Référence de transaction',
        'legal_basis' => 'Base réglementaire',
    ],

    'act' => [
        'title' => "Extrait d'acte de naissance, :reference",
        'banner_body' => 'Ce document ne peut être présenté à aucune administration.',
        'republic' => 'REPUBLIQUE DU CAMEROUN',
        'motto' => 'Paix - Travail - Patrie',
        'heading' => "EXTRAIT D'ACTE DE NAISSANCE",
        'subtitle' => 'Copie rééditée',
        'signed_by' => 'Signé par',
        'signing_authority' => 'Autorité signataire',
        'capacity' => 'Qualité',
        'mayor_of' => 'Maire de :commune',
        'final_notice' => "Ce document a été produit par un adaptateur de signature de démonstration. Il ne résulte d'aucune signature électronique agréée. La valeur légale d'un acte d'état civil signé électroniquement au Cameroun, ainsi que les exigences d'agrément du prestataire de signature, restent à confirmer : voir le bloc A de docs/COMPLIANCE_OPEN_QUESTIONS.md.",
        'verification_title' => 'Vérifier cette copie',
        'verification_body' => "Toute administration, école, employeur ou ambassade qui reçoit cette copie peut vérifier qu'elle est authentique sur :adresse, à l'aide du code ci-dessous. Aucun compte n'est nécessaire.",
    ],

    'draft' => [
        'title' => "Projet d'acte, :reference",
        'banner_body' => "Ce document n'est pas un acte. Il ne porte aucune signature et n'a aucune valeur juridique.",
        'written_by' => 'Rédigé par',
        'officer' => "Officier d'état civil",
        'draft_heading' => "PROJET D'EXTRAIT D'ACTE DE NAISSANCE",
        'draft_subtitle' => 'Copie rééditée, projet soumis à la signature du maire',
        'banner_awaiting' => "Ce document n'est pas un acte. Il attend la décision du maire.",
        'centre' => 'Centre',
        'written_on' => 'Rédigé le',
        'final_notice' => "Aucune signature n'a été apposée. Ce projet n'a aucune valeur et ne peut être présenté à aucune administration. Seule la décision du maire fait naître l'acte.",
        'note' => 'Ce projet est soumis au maire. Seule sa signature en fait un acte.',
    ],

    'proof' => [
        'title' => 'Preuve de signature, :reference',
        'heading' => 'PREUVE DE SIGNATURE',
        'elements' => 'Éléments de la signature',
        'fingerprint' => 'Empreinte du document (SHA-256)',
        'seal' => 'Référence du scellement',
        'provider' => 'Prestataire',
        'algorithm' => 'Algorithme',
        'signature_reference' => 'Référence de signature',
        'signed_on' => 'Signé le',
        'signatory' => 'Signataire',
        'commune' => 'Commune',
        'legal_value' => 'Valeur juridique',
        'legal_value_yes' => 'Oui',
        'legal_value_no' => 'NON, démonstration',
    ],

    'body' => [
        'holder' => "Titulaire de l'acte",
        'name_at_birth' => 'Nom à la naissance',
        'born_on' => 'Né(e) le',
        'place_of_birth' => 'Lieu de naissance',
        'registration_year' => "Année d'enregistrement",
        'original_number' => "Numéro d'acte d'origine",
        'parentage' => 'Filiation',
        'father' => 'Père',
        'mother' => 'Mère',
        'parents_address' => 'Adresse des parents',
        'issue' => 'Délivrance',
        'centre' => "Centre d'état civil",
        'commune' => 'Commune',
        'request_reference' => 'Référence de la demande',
        'copies_requested' => 'Exemplaires demandés',
        'issued_on' => 'Délivré le',
    ],

    'receipt' => [
        'heading' => 'REÇU DE RÈGLEMENT',
        'subtitle' => "Réédition d'acte d'état civil",
        'final_notice' => "Ce document a été produit par un adaptateur de démonstration. Aucun opérateur de paiement n'a été sollicité et aucune somme n'a changé de main. Il ne vaut ni quittance, ni preuve de paiement.",
    ],
];
