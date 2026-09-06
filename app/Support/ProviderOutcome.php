<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\VerificationResult;

/**
 * Resultat normalise d'un adaptateur externe (docs/INTEGRATIONS.md 1).
 *
 * `Unavailable` est un resultat de PREMIER RANG, pas une exception a rattraper
 * quelque part. Le 9 du brief l'exige : l'indisponibilite d'une base externe
 * ne doit jamais bloquer l'officier sans explication. Elle est enregistree
 * comme resultat d'etape, ce qui debloque la situation sans masquer que la
 * verification n'a pas abouti.
 */
enum ProviderOutcome: string
{
    case Match = 'match';
    case NoMatch = 'no_match';
    case Inconclusive = 'inconclusive';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'Correspondance trouvée',
            self::NoMatch => 'Aucune correspondance',
            self::Inconclusive => 'Résultat non concluant',
            self::Unavailable => 'Service indisponible',
        };
    }

    /** Correspondance avec l'enumeration persistee en base. */
    public function toVerificationResult(): VerificationResult
    {
        return match ($this) {
            self::Match => VerificationResult::Match,
            self::NoMatch => VerificationResult::NoMatch,
            self::Inconclusive => VerificationResult::Inconclusive,
            self::Unavailable => VerificationResult::ProviderUnavailable,
        };
    }
}
