<?php

declare(strict_types=1);

return [
    'steps' => [
        1 => 'Request details',
        2 => 'Identity document check',
        3 => 'Photograph review',
        4 => 'Civil status register search',
        5 => 'Decision',
    ],
    'step_navigation' => 'Navigation between steps',
    'step_number' => 'Step :number',

    'heading' => 'Verification :reference',
    'meta' => 'Applicant: :name. Status: :status.',
    'pass_number' => 'Pass :number, returned by the mayor',
    'shortcuts' => 'Shortcuts: :next or n for the next step, :previous or p for the previous one.',
    'previous_step' => 'Previous step',
    'next_step' => 'Next step',
    'back_to_queue' => 'Back to the queue',

    'claim_title' => 'File to take on',
    'claim_body' => 'Nobody is handling this file. Take it on to check it and decide.',
    'claim_action' => 'Take this file on',
    'read_only_title' => 'Read only',
    'read_only_body' => 'This file is being handled by :name. You may read it, and that reading is logged, but the decision is theirs.',
    'unassigned_title' => 'File with no agent',
    'unassigned_body' => 'This file is at status :status and is assigned to nobody, so it can be neither taken on nor decided as it stands. Report it to the administrator.',

    'not_recorded' => 'Not recorded',
    'in_progress' => 'In progress',
    'step_column' => 'Step',
    'summary_title' => 'Summary of the checks',
    'incomplete_title' => 'Verification incomplete',
    'incomplete_body' => 'You cannot accept this request until all four checks have a result. Missing: :steps.',
    'incomplete_note' => 'Rejecting and referring to the mayor remain possible.',
];
