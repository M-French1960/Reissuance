<?php

declare(strict_types=1);

namespace App\Integrations\Fake;

use App\Contracts\CivilRegistryProvider;
use App\Support\ProviderOutcome;
use App\Support\ProviderResponse;

/**
 * Adaptateur factice du registre d'etat civil.
 *
 * Deterministe, declenche par le nom recherche. Couvre notamment le cas des
 * homonymes et celui d'un acte detruit — ce dernier etant precisement la
 * raison d'etre de la plateforme.
 */
final class FakeCivilRegistryProvider implements CivilRegistryProvider
{
    public const PROVIDER = 'fake-registry';

    public function search(array $criteria): ProviderResponse
    {
        $name = mb_strtoupper(trim((string) ($criteria['full_name'] ?? '')));

        if (str_contains($name, 'INTROUVABLE')) {
            return ProviderResponse::make(
                ProviderOutcome::NoMatch, self::PROVIDER, ['records' => []],
                'Aucun acte ne correspond à ces critères dans le registre.',
            );
        }

        if (str_contains($name, 'HOMONYME')) {
            return ProviderResponse::make(
                ProviderOutcome::Inconclusive, self::PROVIDER,
                ['records' => [
                    $this->record($criteria, 'REG-1990-000411'),
                    $this->record($criteria, 'REG-1990-000873'),
                ]],
                'Plusieurs actes correspondent. Un contrôle humain est nécessaire pour les départager.',
            );
        }

        if (str_contains($name, 'DETRUIT')) {
            return ProviderResponse::make(
                ProviderOutcome::Inconclusive, self::PROVIDER,
                ['records' => [$this->record($criteria, 'REG-1990-000112', 'destroyed')]],
                "L'acte est référencé mais son registre est marqué détruit.",
            );
        }

        if (str_contains($name, 'PANNE')) {
            return ProviderResponse::make(
                ProviderOutcome::Unavailable, self::PROVIDER, [],
                'Le registre est momentanément injoignable.',
            );
        }

        return ProviderResponse::make(
            ProviderOutcome::Match, self::PROVIDER,
            ['records' => [$this->record($criteria, 'REG-'.($criteria['registration_year'] ?? '0000').'-000042')]],
            'Un acte unique correspond aux critères.',
        );
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function record(array $criteria, string $number, string $status = 'active'): array
    {
        return [
            'certificate_number' => $number,
            'full_name' => $criteria['full_name'] ?? null,
            'date_of_birth' => $criteria['date_of_birth'] ?? null,
            'place_of_birth' => $criteria['place_of_birth'] ?? null,
            'registration_year' => $criteria['registration_year'] ?? null,
            'record_status' => $status,
        ];
    }
}
