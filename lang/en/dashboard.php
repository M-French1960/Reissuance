<?php

declare(strict_types=1);

return [
    'citizen' => [
        'title' => 'My account',
        'heading' => 'My account',
        'lede' => 'Follow your applications for a reissued birth certificate.',
        'profile_incomplete_title' => 'Complete your profile',
        'profile_incomplete_body' => 'Your profile has to be complete before you can submit an application.',
        'complete_profile' => 'Complete my profile',
        'requests_title' => 'My requests',
        'empty_title' => 'No requests yet',
        'empty_body' => 'Submit your first application for a reissued birth certificate.',
        'apply' => 'Apply for a certificate',
        'apply_again' => 'Submit another application',
        'all_requests' => 'All my requests',

        'welcome_named' => 'Hello, :name',
        'welcome_anonymous' => 'Welcome',
        'welcome_lede' => 'Follow your applications, read what the officer writes to you and download your certificate, all from here.',

        'stat_active' => 'Applications in progress',
        'stat_delivered' => 'Certificates issued',
        'stat_see_all' => 'See my applications',

        'action_title' => 'Have a birth certificate reissued',
        'action_body' => 'For a certificate that was lost or damaged.',

        'resume_draft' => 'Finish my draft application',

        'track_title' => 'Where my application stands',
        'track_reference' => 'Number',
        'track_centre' => 'Centre',
        'track_submitted' => 'Submitted on',
        'track_open' => 'Open the application',
        'track_empty' => 'You have no application in progress. Start one and every step will be shown here, from submission to the copy being issued.',

        'checklist_title' => 'What to prepare',
        'checklist_id' => 'A valid identity document',
        'checklist_photo' => 'A photo of yourself',
        'checklist_details' => 'The certificate details and the civil status centre',
        'checklist_email' => 'The email address that receives the notifications',

        'activity_title' => 'Recent activity',
        'activity_empty' => 'Updates on your applications will appear here.',
        'activity_all' => 'See all',

        'payments_title' => 'Payments',
        'payments_empty' => 'No payment yet. You are asked to pay only if this installation requires it, and the amount is shown to you first.',
        'payments_all' => 'See my applications',
        'payments_request' => 'Application',
        'payments_amount' => 'Amount',
        'payments_method' => 'Method',
        'payments_date' => 'Date',
        'payments_status' => 'Status',
    ],

    'officer' => [
        'title' => 'Officer desk',
        'heading' => 'Verification desk',
        'centre_line' => 'Your centre is :centre. You only see requests from this centre.',
        'counters_note' => 'Each counter opens the queue filtered on that status.',
        'queue_title' => 'Processing queue',
        'queue_body' => 'Take a file, run the five checks, then decide. Everything you open is recorded in the audit log.',
        'open_queue' => 'Open the queue',
        /* --- Reprise de la maquette officier (D-080) --- */
        'welcome' => 'Hello, :name',
        'welcome_lede' => 'Check the reissue applications of your centre before they go to the mayor for signature.',
        'oldest' => 'Open the oldest waiting',
        'counters_title' => 'Your centre right now',
        'hint_pending' => 'Waiting for an officer to take it',
        'hint_under_review' => 'Taken, checks under way',
        'hint_awaiting_signature' => 'Nothing left for you to do',
        'hint_escalated' => 'Waiting for the mayor to arbitrate',
        'hint_signed' => 'File closed',
        'hint_rejected' => 'The applicant was told why',
        'scope_note' => 'These counts cover your centre only. The platform does not let you see another one.',
    ],

    'mayor' => [
        'title' => 'Mayor desk',
        'heading' => 'Signing certificates',
        'commune_line' => 'Your commune is :commune. You only see requests that are ready to sign or referred to you.',
        'sign_title' => 'Sign a certificate',
        'sign_body' => 'You sign the :strong. Open it before you decide. Every signature asks for your authentication code, or for your device if you enrolled one.',
        'sign_body_strong' => 'draft written by the officer',
        'open_signing_queue' => 'Open the signing queue',
        'enrol_device' => 'Enrol a device for signing',
    ],

    'admin' => [
        'title' => 'Administration',
        'heading' => 'Administration',
        'scope_title' => 'What this account can reach',
        'scope_body' => 'Your role gives access to account management and to the metadata of the audit log. It gives access to :strong citizen file: no identity document, no photograph, no document number.',
        'scope_strong' => 'no',
        'accounts_by_role' => 'Accounts by role and status',
        'accounts_distribution' => 'Distribution of accounts',
        'count' => 'Count',
        'manage_accounts' => 'Manage accounts',
        'manage_accounts_body' => 'Create, activate, suspend or reassign an officer or a mayor.',
        'audit_body' => 'Who opened which file and when. The contents of the files stay out of reach.',
        'view' => 'View',
    ],
];
