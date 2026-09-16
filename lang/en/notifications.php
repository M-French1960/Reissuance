<?php

declare(strict_types=1);

return [
    'unread_count' => '{1} unread|[2,*] unread',
    'title' => 'Notifications',
    'detail_note' => 'The full detail of a request, including the reason for a rejection, is on the request page. These messages do not repeat it.',
    'mark_all_read' => 'Mark the :count unread as read|Mark the :count unread as read',
    'all_marked_read' => 'All your notifications are marked as read.',
    'unread_badge' => 'Unread',

    'mail' => [
        'subject' => 'Request :reference: :title',
        'greeting' => 'Hello,',
        'action' => 'View my request',
        'automatic' => 'This message is automatic. Please do not reply to it.',
        'salutation' => 'The civil status certificate reissue service',
    ],

    'titles' => [
        'draft' => 'Request in draft',
        'pending' => 'Request received',
        'under_review' => 'File taken on',
        'returned' => 'File sent back for checking',
        'awaiting_signature' => 'Sent to the mayor',
        'escalated' => 'Referred to the mayor',
        'signed' => 'Your certificate is available',
        'rejected' => 'Request rejected',
        'cancelled' => 'Request cancelled',
    ],

    'bodies' => [
        'draft' => 'Your request :reference is a draft.',
        'pending' => 'Your request :reference has reached the civil status centre. You will be told at every step.',
        'under_review' => 'A civil status officer has taken on your request :reference and is carrying out the checks.',
        'returned' => 'Your request :reference has been sent back to the civil status officer for further checking.',
        'awaiting_signature' => 'Your request :reference has been accepted by the civil status officer and awaits the mayor signature.',
        'escalated' => 'Your request :reference has been referred to the mayor for particular examination.',
        'signed' => 'Your certificate is ready for request :reference. Sign in to download it.',
        'rejected' => 'Your request :reference has been rejected. The reason is on your request page.',
        'cancelled' => 'Your request :reference has been cancelled. You can submit a new one at any time.',
    ],

    'none_title' => 'No notifications',
    'none_citizen' => 'You will be told here at every step of your requests.',
    'none_officer' => 'You will be told here when the mayor returns a file to you. Requests waiting to be processed are in the processing queue.',
    'none_other' => 'Nothing has been reported to you yet.',
];
