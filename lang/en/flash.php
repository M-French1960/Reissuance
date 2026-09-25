<?php

declare(strict_types=1);

/*
 * Messages a controller or a service sends back after an action: flashes,
 * validation messages, and refusals.
 *
 * They live here rather than inline because a string written into a
 * controller can only ever exist in one language, and half of these are read
 * by an agent deciding whether to issue a civil status certificate.
 */
return [
    'mayor' => [
        'reason_required_exception' => 'Approving a referred request by exception requires a reason: it will appear in the file and in the audit log.',
        'reason_min' => 'The reason has to be explicit enough: at least 10 characters.',
        'cannot_sign_incomplete' => 'This request cannot be signed: the verification is incomplete. Missing: :steps.',
        'cannot_sign_unpaid' => 'This request cannot be signed: the fee has not been paid. The applicant has to pay before the signature.',
        'issued' => 'Certificate issued for request :reference.',
        'demo_warning' => 'Note: the document produced carries the words "no legal value".',
        'reject_reason_required' => 'A reason for the rejection is compulsory: it will be passed on to the citizen.',
        'rejected' => 'Request :reference rejected.',
        'return_reason_required' => 'Tell the officer what has to be redone: this reason is their only instruction.',
        'returned' => 'Request :reference returned to the officer for a fresh check.',
    ],

    'officer' => [
        'decision_required' => 'Choose a decision.',
        'reason_required' => 'A reason is compulsory to reject a request or refer it to the mayor. It will be visible in the file.',
        'reason_required_reservation' => 'One check did not return a match. Whatever your decision, a reason is compulsory: it will appear in the file and be read by the mayor.',
        'reason_min' => 'The reason has to be explicit enough: at least 10 characters.',
        'cannot_accept_incomplete' => 'You cannot accept this request until all four checks have a result. Missing: :steps.',
        'decision_recorded' => 'Request :reference: :decision.',
        'next_open' => 'Open :reference',
        'next_waiting' => 'Another request is waiting to be taken on.',
        'result_required' => 'Record the result of your check before continuing.',
        'run_facial_first' => 'Run the facial comparison before concluding on the photographs.',
    ],

    'citizen' => [
        'complete_profile_first' => 'Complete your profile before submitting a request.',
        'missing_attachment' => 'Your request cannot be sent without :label.',
        'registration_year_min' => 'The year of registration has to be four digits, for example 1990.',
        'registration_year_max' => 'The year of registration cannot be in the future.',
        'birth_date_before' => 'The date of birth has to be earlier than today.',
        'centre_required' => 'Choose the civil status centre where the certificate was registered.',
        'centre_unavailable' => 'That centre is no longer receiving requests. Choose another one from the list.',
        'photo_saved' => 'The photo has been saved.',
        'file_mimetypes' => 'The file has to be a JPEG, PNG or WebP image.',
        'file_max' => 'The file is too large. Take the photo again: it will be compressed automatically.',
        'file_required' => 'No file was received. Take the photo again, then try once more.',
        'selfie_label' => 'your photo',
        'id_label' => 'your identity document',
    ],

    'payment' => [
        'operator_required' => 'Choose how you would like to pay.',
        'payer_reference_required' => 'Give the number you are paying from.',
        'none_running' => 'No payment is running.',
    ],

    'messages' => [
        'body_required' => 'Write your message before sending it.',
        'body_max' => 'Your message is too long: 2000 characters at most.',
    ],

    'notifications' => [
        'all_read' => 'All your notifications are marked as read.',
    ],

    'admin' => [
        'role_reason' => 'Role: :role',
        'status_reason_min' => 'Give the reason for this change: it will appear in the audit log.',
        'cannot_activate_without_2fa' => 'This account cannot be activated until its holder has set up two-factor authentication.',
        'assignment_released' => 'Assignment released: :name can no longer work on this file.',
    ],

    'signature' => [
        'blocked' => 'Too many wrong codes. Signing is blocked for :minutes minutes. If these attempts were not yours, tell the administrator.',
        'code_required' => 'Enter the code from your authentication app to sign.',
        'two_factor_missing' => 'Your two-factor authentication is not set up, so signing is unavailable. Set it up from the Security page.',
        'code_incorrect' => 'Incorrect code. Check the code shown by your authentication app, or use one of your recovery codes.',
    ],

    'password' => [
        'too_common' => 'This password contains a term that is too common. Choose a string of words unrelated to the service.',
        'too_simple' => 'This password follows too simple a sequence. Vary the characters.',
    ],

    'device' => [
        'label_required' => 'Give this device a name: you will need to recognise it to revoke it.',
        'enrolled' => 'The device ":label" can now sign.',
        'revoked' => 'The device ":label" can no longer sign.',
        'domain_missing' => 'The domain for signing devices is not configured. Set APP_URL, or PHOENIX_WEBAUTHN_RP_ID.',
        'domain_mismatch' => 'Device signing is configured for the domain ":declared", and this page is served from ":actual". Enrolment and signing will be refused by the browser. Set PHOENIX_WEBAUTHN_RP_ID to the domain actually in use.',
        'not_enrolment' => 'The device response is not an enrolment response.',
        'enrolment_failed' => 'The device could not be enrolled: :reason',
        'already_enrolled' => 'This device is already enrolled.',
        'none_enrolled' => 'No device is enrolled on your account. Use your authentication code.',
    ],
];
