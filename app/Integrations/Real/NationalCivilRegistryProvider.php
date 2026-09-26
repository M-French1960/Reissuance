<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\CivilRegistryProvider;
use App\Support\ProviderResponse;
use RuntimeException;

/**
 * Voir DgsnIdentityLookupProvider : echoue bruyamment plutot que de mentir.
 *
 * L'INTERLOCUTEUR A UN NOM DEPUIS D-091 : le BUNEC.
 *
 * L'article 10 (nouveau) de la loi n 2011/011 du 6 mai 2011 institue un
 * « bureau national de l'etat civil », charge notamment « de la constitution
 * et de la gestion du FICHIER NATIONAL DE L'ETAT CIVIL ». C'est donc lui que
 * cet adaptateur interrogerait, et c'est avec lui que l'acces se negocie.
 *
 * L'article 18 (nouveau) dit d'ou vient ce fichier : les registres sont tenus
 * en TROIS exemplaires, dont un transmis au bureau national. Le registre papier
 * reste l'original ; ce que PHOENIX delivre est une COPIE.
 *
 * Ce que le texte ne dit pas, et qui reste ouvert : a quelles conditions un
 * tiers peut interroger ce fichier. Cela ne se lit pas, cela se negocie.
 */
final class NationalCivilRegistryProvider implements CivilRegistryProvider
{
    public function search(array $criteria): ProviderResponse
    {
        throw new RuntimeException(
            "L'adaptateur réel du registre d'état civil n'est pas implémenté. "
            ."L'interlocuteur est identifié depuis D-091 : le bureau national de l'état "
            ."civil (BUNEC), chargé par l'article 10 (nouveau) de la loi n° 2011/011 du "
            .'6 mai 2011 « de la constitution et de la gestion du fichier national de '
            ."l'état civil ». Restent ouvertes les conditions d'accès d'un tiers à ce "
            .'fichier, qui ne se lisent dans aucun texte public et se négocient : voir '
            .'docs/INTEGRATIONS.md §3 et la question C2. '
            .'Utilisez PHOENIX_REGISTRY_PROVIDER=fake.'
        );
    }
}
