<?php

declare(strict_types=1);

/*
 * Labels for the domain enumerations.
 *
 * The status labels avoid the dash-and-clause shape the French ones used
 * ("Signée — acte disponible"). A status reads better as one short phrase,
 * and the extra information belongs next to it, not inside it.
 */
return [
    'request_status' => [
        'draft' => 'Draft, not submitted',
        'pending' => 'Submitted, awaiting processing',
        'under_review' => 'Being checked',
        'awaiting_signature' => 'Awaiting the mayor signature',
        'escalated' => 'Referred to the mayor',
        'signed' => 'Signed, certificate available',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled by the applicant',
    ],

    'user_role' => [
        'citizen' => 'Citizen',
        'officer' => 'Civil status officer',
        'mayor' => 'Mayor',
        'admin' => 'Administrator',
    ],

    'decision' => [
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'escalated' => 'Referred to the mayor',
        'signed' => 'Signed',
        'approved_by_exception' => 'Approved by exception',
        'returned' => 'Returned to the officer',
    ],

    'verification_result' => [
        'match' => 'Match found',
        'no_match' => 'No match',
        'inconclusive' => 'Inconclusive',
        'provider_unavailable' => 'External service unavailable',
        'not_recorded' => 'Not recorded',
    ],

    'payment_status' => [
        'pending' => 'Awaiting confirmation',
        'authorised' => 'Authorised, funds not yet received',
        'settled' => 'Paid',
        'failed' => 'Declined',
        'expired' => 'Expired without an answer',
        'refunded' => 'Refunded',
    ],

    'payment_operator' => [
        'orange_money' => 'Orange Money',
        'mtn_mobile_money' => 'MTN Mobile Money',
        'orange_money_hint' => 'The Orange number you are paying from.',
        'mtn_mobile_money_hint' => 'The MTN number you are paying from.',
    ],

    'account_status' => [
        'active' => 'Active',
        'pending' => 'Awaiting setup',
        'suspended' => 'Suspended',
        'disabled' => 'Disabled',
    ],
];
