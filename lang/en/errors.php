<?php

declare(strict_types=1);

return [
    'back_to_account' => 'Back to my account',
    'back_home' => 'Back to the home page',

    '403' => [
        'title' => 'Access refused',
        'body' => 'Your account cannot reach this page. If you believe this is a mistake, speak to the administration of your commune.',
    ],
    '404' => [
        'title' => 'Page not found',
        'body' => 'This page does not exist, or it is not available to you. If you followed a link sent by email, it may have expired.',
        'note' => 'Your ongoing requests are still available from your account.',
    ],
    '419' => [
        'title' => 'Your session has expired',
        'body' => 'You stayed on the page too long before sending it. For safety, sessions on official accounts are short.',
        'note' => 'Sign in again and start over. Whatever you had already confirmed is saved.',
    ],
    '500' => [
        'title' => 'The service has hit a problem',
        'body' => 'An error is stopping us from showing this page. It has been recorded, and no data you had confirmed is lost.',
        'note' => 'Try again in a moment. If it happens again, report it to the administration of your commune.',
    ],
    '503' => [
        'title' => 'Service briefly unavailable',
        'body' => 'The service is under maintenance. Your ongoing requests are not affected and will be waiting for you.',
    ],
];
