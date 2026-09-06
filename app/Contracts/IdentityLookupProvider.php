<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\ProviderResponse;

/**
 * Verification d'une piece d'identite aupres de la base de la police.
 * Etape 2 de la verification (5.3 du brief).
 *
 * ATTENTION — docs/INTEGRATIONS.md 2 : je n'ai aucune documentation d'une
 * API reelle. Ce contrat est une HYPOTHESE de travail, pas la description
 * d'un service existant.
 *
 * La question la plus structurante reste ouverte : une interface machine
 * existe-t-elle seulement ? Si la verification se fait aujourd'hui par
 * telephone ou par consultation d'un terminal dedie, ce contrat ne modelise
 * pas un appel synchrone mais une reponse humaine differee — ce qui changerait
 * le modele de donnees et l'ergonomie de l'officier.
 */
interface IdentityLookupProvider
{
    /**
     * @param  string  $documentNumber  numero de la piece presentee
     * @param  string  $claimedName  nom declare par le demandeur
     */
    public function verify(string $documentNumber, string $claimedName): ProviderResponse;
}
