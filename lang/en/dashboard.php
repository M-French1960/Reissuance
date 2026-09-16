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
    ],

    'officer' => [
        'title' => 'Officer desk',
        'heading' => 'Verification desk',
        'centre_line' => 'Your centre is :centre. You only see requests from this centre.',
        'counters_note' => 'Each counter opens the queue filtered on that status.',
        'queue_title' => 'Processing queue',
        'queue_body' => 'Take a file, run the five checks, then decide. Everything you open is recorded in the audit log.',
        'open_queue' => 'Open the queue',
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
