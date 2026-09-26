<?php

declare(strict_types=1);

return [
    'demo_notice' => 'DEMONSTRATION DOCUMENT - NO LEGAL VALUE',
    'draft_notice' => 'DRAFT CERTIFICATE - UNSIGNED - NO VALUE',

    'receipt_demo_notice' => 'DEMONSTRATION RECEIPT - NO SUM HAS BEEN COLLECTED',
    'receipt_fields' => [
        'request_reference' => 'Request reference',
        'applicant' => 'Applicant',
        'amount_paid' => 'Amount paid',
        'payment_date' => 'Date of payment',
        'payment_method' => 'Payment method',
        'transaction_reference' => 'Transaction reference',
        'legal_basis' => 'Regulatory basis',
    ],

    'act' => [
        'title' => 'Birth certificate extract, :reference',
        'banner_body' => 'This document cannot be presented to any administration.',
        'republic' => 'REPUBLIC OF CAMEROON',
        'motto' => 'Peace - Work - Fatherland',
        'heading' => 'BIRTH CERTIFICATE EXTRACT',
        'subtitle' => 'Reissued copy',
        'signed_by' => 'Signed by',
        'signing_authority' => 'Signing authority',
        'capacity' => 'Capacity',
        'mayor_of' => 'Mayor of :commune',
        'final_notice' => 'This document was produced by a demonstration signature adapter. It results from no approved electronic signature. Whether a civil status certificate signed electronically has legal force in Cameroon, and what approval a signature provider needs, are still to be confirmed: see block A of docs/COMPLIANCE_OPEN_QUESTIONS.md.',
        'verification_title' => 'Checking this copy',
        'verification_body' => 'Any administration, school, employer or embassy receiving this copy may check that it is genuine at :adresse, using the code below. No account is needed.',
    ],

    'draft' => [
        'title' => 'Draft certificate, :reference',
        'banner_body' => 'This document is not a certificate. It carries no signature and no legal value.',
        'written_by' => 'Written by',
        'officer' => 'Civil status officer',
        'draft_heading' => 'DRAFT BIRTH CERTIFICATE EXTRACT',
        'draft_subtitle' => 'Reissued copy, draft submitted for the mayor signature',
        'banner_awaiting' => 'This document is not a certificate. It is waiting on the mayor decision.',
        'centre' => 'Centre',
        'written_on' => 'Written on',
        'final_notice' => 'No signature has been applied. This draft has no value and cannot be presented to any administration. Only the mayor decision brings the certificate into being.',
        'note' => 'This draft is submitted to the mayor. Only the mayor signature turns it into a certificate.',
    ],

    'proof' => [
        'title' => 'Proof of signature, :reference',
        'heading' => 'PROOF OF SIGNATURE',
        'elements' => 'Details of the signature',
        'fingerprint' => 'Document fingerprint (SHA-256)',
        'seal' => 'Seal reference',
        'provider' => 'Provider',
        'algorithm' => 'Algorithm',
        'signature_reference' => 'Signature reference',
        'signed_on' => 'Signed on',
        'signatory' => 'Signatory',
        'commune' => 'Commune',
        'legal_value' => 'Legal value',
        'legal_value_yes' => 'Yes',
        'legal_value_no' => 'NO, demonstration',
    ],

    'body' => [
        'holder' => 'Holder of the certificate',
        'name_at_birth' => 'Name at birth',
        'born_on' => 'Born on',
        'place_of_birth' => 'Place of birth',
        'registration_year' => 'Year of registration',
        'original_number' => 'Original certificate number',
        'parentage' => 'Parentage',
        'father' => 'Father',
        'mother' => 'Mother',
        'parents_address' => 'Parents address',
        'issue' => 'Issue',
        'centre' => 'Civil status centre',
        'commune' => 'Commune',
        'request_reference' => 'Request reference',
        'copies_requested' => 'Copies requested',
        'issued_on' => 'Issued on',
    ],

    'receipt' => [
        'heading' => 'PAYMENT RECEIPT',
        'subtitle' => 'Civil status certificate reissue',
        'final_notice' => 'This document was produced by a demonstration adapter. No payment operator was contacted and no sum changed hands. It is neither a receipt nor proof of payment.',
    ],
];
