<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\CivilRegistryProvider;
use App\Support\ProviderResponse;
use RuntimeException;

/** Voir DgsnIdentityLookupProvider : echoue bruyamment plutot que de mentir. */
final class NationalCivilRegistryProvider implements CivilRegistryProvider
{
    public function search(array $criteria): ProviderResponse
    {
        throw new RuntimeException(
            "L'adaptateur réel du registre d'état civil n'est pas implémenté. "
            .'La question préalable — les registres sont-ils numérisés et interrogeables ? — '
            .'est ouverte : voir docs/INTEGRATIONS.md §3. Utilisez PHOENIX_REGISTRY_PROVIDER=fake.'
        );
    }
}
