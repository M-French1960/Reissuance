<?php

declare(strict_types=1);

/*
 * THE FIRST PAGE OF THE SERVICE (D-072, D-078).
 *
 * Every sentence here is a statement made by an administration to a citizen,
 * so every sentence has to be true of THIS software. The design handed over
 * for this page promised things the application does not do: notification by
 * SMS, a loss declaration from the police station, marriage and death
 * certificates "coming soon", a public tracking box, and two answers left as
 * "[to be completed]". None of it was carried over. What replaced it says what
 * the application actually does, and says plainly what it does not do.
 */
return [
    'title' => 'Home',
    'meta_description' => 'Apply online for a new copy of your birth certificate. Your civil status centre draws it up, your mayor signs it.',

    /* --- Navigation ---------------------------------------------------- */
    'nav_documents' => 'Documents',
    'nav_steps' => 'How it works',
    'nav_checklist' => 'What to prepare',
    'nav_faq' => 'Questions',
    'menu_open' => 'Open the menu',
    'menu_close' => 'Close the menu',

    /* --- Demonstration warning ------------------------------------------ */
    'demo_title' => 'Demonstration service',
    'demo_body' => 'Certificates produced by this installation carry the words :mention and cannot be presented to any administration. Do not start a real application on this basis.',
    'demo_mention' => 'no legal value',

    /* --- Hero ------------------------------------------------------------ */
    'hero_title' => 'Your lost birth certificate, reissued without queuing',
    'hero_lead' => 'Apply online, follow the check by the civil status officer and the signature by the mayor, then download your new copy.',
    'hero_start' => 'Start an application',
    'hero_steps' => 'See the steps',

    'track_label' => 'Have you already applied?',
    'track_body' => 'Following a file is not public, and will not be. Sign in: your applications and their exact stage are in your account.',
    'track_action' => 'Sign in to follow my application',

    /* --- The illustrated card in the hero -------------------------------- */
    'mock_caption' => 'Illustration of the screen you will see. It is not a certificate and it belongs to nobody.',
    'mock_title' => 'Birth certificate',
    'mock_sub' => 'Reissue application, example',
    'mock_holder_label' => 'Holder',
    'mock_holder_value' => 'Your name',
    'mock_center_label' => 'Civil status centre',
    'mock_center_value' => 'The centre you choose',
    'mock_reason_label' => 'Reason',
    'mock_reason_value' => 'Lost',
    'mock_1' => 'Application received',
    'mock_1_note' => 'Documents uploaded',
    'mock_2' => 'Checked by the officer',
    'mock_2_note' => 'Match with the register',
    'mock_3' => 'Signature by the mayor',
    'mock_3_note' => 'In progress',
    'mock_4' => 'Copy available',
    'mock_4_note' => 'Download from your account',
    'mock_badge' => 'You are notified at every step',

    /* --- What can be applied for ----------------------------------------- */
    'docs_title' => 'Which document can be reissued here?',
    'docs_intro' => 'This service handles birth certificates, and only those. For any other civil status certificate, go to your civil status centre.',
    'docs_available' => 'Available',
    'docs_unavailable' => 'Not handled here',
    'docs_birth_title' => 'Birth certificate',
    'docs_birth_body' => 'For a certificate that was lost or damaged. You name the civil status centre that holds it, and the officer looks for it in the register.',
    'docs_birth_cta' => 'Start the application',
    'docs_marriage_title' => 'Marriage certificate',
    'docs_marriage_body' => 'You cannot apply for one here. No date has been set for adding it.',
    'docs_death_title' => 'Death certificate',
    'docs_death_body' => 'You cannot apply for one here. No date has been set for adding it.',

    /* --- The four steps --------------------------------------------------- */
    'steps_title' => 'Four steps, each handled by the right person',
    'steps_intro' => 'You see where your file stands at any moment, and you can cancel it as long as the certificate has not been signed.',
    'step1_title' => 'You submit the application',
    'step1_body' => 'Certificate details, a photo of your identity document and a photo of yourself, from your phone or your computer.',
    'step1_who' => 'You',
    'step2_title' => 'The officer checks it',
    'step2_body' => 'They look for the certificate in the register of the centre, check your identity document and compare your photo. They can write to you if something is missing.',
    'step2_who' => 'Civil status officer',
    'step3_title' => 'The mayor signs',
    'step3_body' => 'The mayor reads the certificate written by the officer, then signs it, or sends the file back for correction.',
    'step3_who' => 'Mayor',
    'step4_title' => 'You collect the copy',
    'step4_body' => 'You receive an email and a notification in your account, then you download the certificate.',
    'step4_who' => 'You',

    /* --- What to prepare --------------------------------------------------- */
    'bring_title' => 'Prepare these before you start',
    'bring_intro' => 'Having everything to hand lets you finish the application in one sitting.',
    'bring_id_title' => 'A valid identity document',
    'bring_id_desc' => 'Photographed so that it can be read. The file is stored outside the public web space, it has no address anyone can guess, and every time an agent opens it that is recorded.',
    'bring_photo_title' => 'A photo of yourself',
    'bring_photo_desc' => 'Taken with your phone, facing the camera. It is compared with your identity document.',
    'bring_details_title' => 'The certificate details',
    'bring_details_desc' => 'Civil status centre, date and place of birth, year of registration, your parents names. The certificate number if you have it.',
    'bring_email_title' => 'An email address',
    'bring_email_desc' => 'It receives the notifications. There is no notification by SMS on this service.',
    'bring_note' => 'Photos are reduced on your phone before being sent, so they use less data.',

    'aside_title' => 'You can stop and come back',
    'aside_body' => 'Each of the four steps is saved as you complete it. If your connection drops or your battery runs out, you pick the application up where you left it.',
    'aside_cta' => 'Create an account',

    /* --- Why you can trust the service -------------------------------------- */
    'trust_title' => 'A birth certificate is a sensitive document',
    'trust_intro' => 'The platform is built accordingly, and these three statements can be checked in the software itself.',
    'trust1_title' => 'Access is restricted',
    'trust1_body' => 'Only the officer of the centre you chose and the mayor of that commune can open your file. Nobody else on the platform can.',
    'trust2_title' => 'Every certificate is signed',
    'trust2_body' => 'The copy issued carries the signature of the mayor and a proof that can be checked afterwards.',
    'trust3_title' => 'Nothing happens without a trace',
    'trust3_body' => 'Every action on your application is dated, attributed and recorded. The record cannot be altered from the application.',

    /* --- Questions ----------------------------------------------------------- */
    'faq_title' => 'Questions you may have',
    'faq_intro' => 'Once you have an account, you can write to the officer handling your file from the file itself.',

    'faq_q1' => 'How long does a reissue take?',
    'faq_a1' => 'No processing time is published, because it depends on the civil status centre and on the search in the register. Rather than announce a figure that would not be kept, the service shows you the exact stage your application has reached, at any time, from your account.',

    'faq_q2' => 'How much does it cost and how do I pay?',
    'faq_a2' => 'The fee is set by the administration running this installation, not by the platform. If a payment is required, the amount is shown to you before you pay, and the payment is made by Orange Money or MTN Mobile Money. Nothing is ever taken without you seeing the amount first.',

    'faq_q3' => 'Is my civil status centre covered?',
    'faq_a3' => 'The list of connected centres is shown at the third step of the form. If yours is not in it, the application cannot be made here yet, and the platform will not let you choose another centre in its place.',

    'faq_q4' => 'What happens if a document is missing?',
    'faq_a4' => 'The officer writes to you from your file. You are notified, and you read and answer the message in your account. One limit to know about: once an application has been sent, its photos can no longer be replaced from your account, so take the time to check that yours can be read before you send.',

    'faq_q5' => 'Are you a civil status officer, a mayor or an administrator?',
    'faq_a5' => 'Your account is created by the administration and cannot be opened from this page. Sign in with the address you were given.',

    /* --- Final call ------------------------------------------------------------ */
    'final_title' => 'Ready to have your certificate reissued?',
    'final_body' => 'Create your account, then follow the four steps of the form.',
    'final_cta' => 'Start an application',

    /* --- Footer -------------------------------------------------------------- */
    'footer_tag' => 'Online reissue of civil status certificates.',
    'footer_service' => 'The service',
    'footer_help' => 'Help',
    'footer_birth' => 'Birth certificate',
    'footer_track' => 'Follow an application',
    'footer_signin' => 'Sign in',
    'footer_status' => 'Service status',
    'footer_year' => 'PHOENIX, :year',
];
