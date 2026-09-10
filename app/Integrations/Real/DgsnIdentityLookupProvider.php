<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\IdentityLookupProvider;
use App\Support\ProviderResponse;
use RuntimeException;

/**
 * Delegation Generale a la Surete Nationale (DGSN) — l'acteur « GDNS » du
 * diagramme de cas d'utilisation.
 *
 * C'est l'administration camerounaise qui delivre la carte nationale
 * d'identite ; c'est donc elle que la verification d'une piece interroge.
 * Confirme par recherche : https://www.dgsn.cm/
 *
 * Squelette de l'adaptateur reel.
 *
 * Il LEVE une exception explicite tant qu'il n'est pas implemente — jamais un
 * retour muet, jamais une valeur par defaut optimiste. Un adaptateur non
 * implemente qui renverrait « correspondance trouvee » serait exactement le
 * chemin par lequel un acte frauduleux sort du systeme (docs/INTEGRATIONS.md 1).
 */
final class DgsnIdentityLookupProvider implements IdentityLookupProvider
{
    public function verify(string $documentNumber, string $claimedName): ProviderResponse
    {
        throw new RuntimeException(
            "L'adaptateur réel de vérification d'identité n'est pas implémenté. "
            ."Aucune documentation d'API n'a été obtenue à ce jour : voir les questions "
            .'ouvertes de docs/INTEGRATIONS.md §2. Utilisez PHOENIX_IDENTITY_PROVIDER=fake.'
        );
    }
}
