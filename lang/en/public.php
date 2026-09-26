<?php

declare(strict_types=1);

return [
    'verify' => [
        'title' => 'Check that a certificate is genuine',
        'lede' => 'For administrations, schools, employers and embassies: confirm that a copy issued by this service is genuine. No account is needed.',
        'code' => 'Verification code',
        'code_hint' => 'Printed at the bottom of the copy, under "Checking this copy". Letters and digits, in any spacing.',
        'check' => 'Check',
        'genuine_title' => 'This certificate is genuine',
        'genuine_body' => 'Compare what follows with the document in front of you. If anything differs, the copy is not the one this service issued.',
        'unknown_title' => 'No certificate carries this code',
        'unknown_body' => 'Check that the code was copied correctly: letters and digits only, and the letters O and I never appear in a code. If it still does not match, the document was not issued by this service.',
        'reference' => 'Request reference',
        'centre' => 'Issued by',
        'issued_on' => 'Issued on',
        'initials' => 'Holder, initials',
        'birth_year' => 'Year of birth',
        'privacy_note' => 'Only what is needed to compare is shown. A code alone tells nobody who the certificate belongs to.',
        'demo_title' => 'This certificate has no legal value',
        'demo_body' => 'It was produced while the service runs on its demonstration signature provider. It is genuine in the sense that this service issued it, and it is not a legal instrument.',
        'no_revocation' => 'This service records no revocation of certificates: this answer says that the certificate was issued and by whom, not that it is still valid today.',
        'not_printed' => 'Certificates signed before this check existed carry no printed code. Their holder can still download them from their account.',
    ],
];
