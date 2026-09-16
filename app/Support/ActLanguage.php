<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ReissuanceRequest;

/**
 * Which language a certificate is issued in.
 *
 * WHAT WE COULD ESTABLISH. English and French are official languages of equal
 * standing in Cameroon (Constitution of 18 January 1996), and the Act of
 * 24 December 2019 on the promotion of official languages requires both to be
 * usable in public administrations and in decentralised local authorities,
 * which is what a commune is.
 *
 * WHAT WE COULD NOT. Whether an individual birth certificate is drawn up in
 * one language, bilingually, or at the applicant's choice. No source we could
 * consult says so, and §10 of the brief forbids coding a legal assumption. The
 * rule is therefore a deployment setting the administration decides, and the
 * question is recorded in block A of docs/COMPLIANCE_OPEN_QUESTIONS.md.
 *
 * WHATEVER THE SETTING, the language is frozen on the request at submission.
 * A signed certificate never changes language afterwards: its content
 * fingerprint binds the signature to the exact text, and a certificate that
 * read French for one person and English for another would no longer match
 * what the mayor signed.
 */
final class ActLanguage
{
    /** Follows whoever applied. */
    public const FOLLOW_REQUESTER = 'requester';

    /** The language to freeze on a request being submitted now. */
    public static function forNewRequest(): string
    {
        $regle = (string) config('phoenix.documents.language', self::FOLLOW_REQUESTER);

        return $regle === self::FOLLOW_REQUESTER
            ? app()->getLocale()
            : Locales::orDefault($regle);
    }

    /**
     * The language a given request's documents print in.
     *
     * A request submitted before the column existed carries none. It falls
     * back to the platform default rather than pretending to a choice nobody
     * made.
     */
    public static function forRequest(ReissuanceRequest $demande): string
    {
        return Locales::orDefault($demande->act_language);
    }
}
