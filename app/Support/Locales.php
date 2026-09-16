<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The languages this platform serves.
 *
 * Cameroon has two official languages of equal standing, English and French
 * (Constitution of 18 January 1996). The Act of 24 December 2019 on the
 * promotion of official languages requires both to be usable in public
 * administrations and in decentralised local authorities, which is exactly
 * what a commune is. Serving one language only would put the platform at odds
 * with that.
 *
 * English is the default here because the client asked for it, not because
 * either language ranks above the other.
 */
final class Locales
{
    public const DEFAULT = 'en';

    /** @var array<string, string> code => the language's own name */
    public const SUPPORTED = [
        'en' => 'English',
        'fr' => 'Français',
    ];

    public static function isSupported(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::SUPPORTED);
    }

    /** Falls back to the default rather than throwing: a bad code is not an outage. */
    public static function orDefault(?string $code): string
    {
        return self::isSupported($code) ? $code : self::DEFAULT;
    }

    /**
     * Picks a language from an Accept-Language header.
     *
     * Only the primary subtag is read: `en-GB`, `en-US` and `en` are one
     * language here. Quality values are honoured in the order the browser
     * sends them.
     *
     * @param  list<string>  $preferred  already ordered by preference
     */
    public static function fromBrowser(array $preferred): ?string
    {
        foreach ($preferred as $tag) {
            $primary = strtolower(explode('-', trim($tag))[0]);

            if (self::isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
