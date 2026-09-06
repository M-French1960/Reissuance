<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationResult: string
{
    case Match = 'match';
    case NoMatch = 'no_match';
    case Inconclusive = 'inconclusive';

    /**
     * Resultat de premier rang, pas une exception.
     *
     * Le 9 du brief exige que l'indisponibilite d'une base externe ne bloque
     * pas l'officier sans explication : elle est enregistree comme un resultat,
     * ce qui debloque la situation sans masquer que la verification n'a pas
     * abouti.
     */
    case ProviderUnavailable = 'provider_unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'Correspondance trouvée',
            self::NoMatch => 'Aucune correspondance',
            self::Inconclusive => 'Résultat non concluant',
            self::ProviderUnavailable => 'Service externe indisponible',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Match => 'success',
            self::NoMatch => 'danger',
            self::Inconclusive => 'attention',
            self::ProviderUnavailable => 'neutral',
        };
    }
}
