<?php

declare(strict_types=1);

return [
    'requests_title' => 'My requests',
    'requests_empty_title' => 'No requests yet',
    'requests_empty_body' => 'You have not yet submitted an application for a reissued birth certificate.',

    'profile' => [
        'title' => 'My profile',
        'intro' => 'These details identify you to the civil status centre. They are carried into your applications automatically.',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'birth_date' => 'Date of birth',
        'birth_place' => 'Place of birth',
        'national_id' => 'Identity document number',
        'national_id_hint' => 'Optional. This number is encrypted: it can be read neither in the database nor by the service administration.',
        'phone' => 'Phone',
        'phone_hint' => 'For example +237 6 XX XX XX XX.',
        'address' => 'Address',
        'saved' => 'Your profile has been saved.',
    ],

    'tracking' => [
        'title' => 'Tracking my request',
        'heading' => 'Request :reference',
        'issued_by' => 'Handled by :centre',
        'timeline_title' => 'Where my request stands',
        'will_be_told' => 'You will be told at every change of step.',
        'rejected_title' => 'Why this request was rejected',

        'milestone_submitted' => 'Request submitted',
        'milestone_submitted_detail' => 'Sent to :centre.',
        'milestone_submitted_detail_generic' => 'Sent to the civil status centre.',
        'milestone_checked' => 'Officer check',
        'milestone_checked_detail' => 'Your identity is checked and the original certificate is looked up.',
        'milestone_mayor' => 'Mayor decision',
        'milestone_mayor_detail' => 'Your reissued certificate is signed.',
        'milestone_available' => 'Certificate available',
        'milestone_available_ready' => 'Your certificate is ready. Download it below.',
        'milestone_available_pending' => 'You will be able to download your certificate.',

        'state_done' => 'done',
        'state_current' => 'in progress',
        'state_stopped' => 'not reached',
        'state_upcoming' => 'to come',

        'my_certificate' => 'My certificate',
        'signed_on' => 'Your certificate was signed on :date by :mayor.',
        'demo_title' => 'Demonstration document',
        'demo_body' => 'This document carries the words "no legal value" and cannot be presented to any administration. The platform is running with a demonstration signature provider.',
        'download' => 'Download my certificate',
        'signature_proof' => 'Proof of signature',

        'my_documents' => 'My documents',
        'no_documents' => 'No documents saved.',
        'your_photo' => 'Your photo',
        'your_id' => 'Your identity document',
        'size_kb' => ':size KB',

        'cancel_title' => 'Cancel my request',
        'cancel_body' => 'You can withdraw this request as long as no agent has taken it on. :strong to start again you would have to submit a new request.',
        'cancel_strong' => 'This cannot be undone:',
        'cancel_reason' => 'Reason',
        'cancel_reason_hint' => 'Optional. It helps the civil status centre understand.',
        'cancel_action' => 'Cancel my request',
        'cancelled' => 'Your request :reference has been cancelled.',
        'cancelled_paid' => 'A fee was paid for this request. Contact your civil status centre, since what happens next depends on the refund rules in force.',

        'back_to_requests' => 'Back to my requests',
    ],
];
