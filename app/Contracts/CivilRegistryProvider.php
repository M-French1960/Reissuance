<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\ProviderResponse;

/**
 * Recherche de l'acte d'origine dans le registre d'etat civil.
 * Etape 4 de la verification (5.3 du brief).
 *
 * ATTENTION — docs/INTEGRATIONS.md 3 : contrat hypothetique.
 *
 * Question ouverte determinante : les registres sont-ils numerises ? S'ils
 * sont sur papier dans les centres, l'etape 4 n'est pas une recherche
 * automatisee mais la SAISIE PAR L'OFFICIER du resultat d'une consultation
 * physique. Le systeme devrait alors enregistrer une declaration d'agent, pas
 * une reponse de service.
 */
interface CivilRegistryProvider
{
    /**
     * @param  array{full_name?: string, date_of_birth?: string, place_of_birth?: string, registration_year?: int|null, certificate_number?: string|null}  $criteria
     */
    public function search(array $criteria): ProviderResponse;
}
