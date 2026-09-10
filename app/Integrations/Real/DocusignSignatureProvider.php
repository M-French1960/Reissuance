<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\SignatureProvider;
use App\Support\SignatureResult;
use RuntimeException;

/**
 * Squelette de l'adaptateur Docusign — l'acteur « Docusign API » du diagramme
 * de cas d'utilisation.
 *
 * Ce que la designation d'un produit REGLE : l'interface existe et elle est
 * documentee. L'API eSignature REST de Docusign travaille par « enveloppes »
 * contenant des documents et des destinataires ; on cree une enveloppe, on
 * suit son statut, on recupere le document signe.
 * Reference : https://developers.docusign.com/docs/esign-rest-api/
 *
 * CE QU'ELLE NE REGLE PAS, et c'est le point :
 *
 *  1. **La valeur juridique.** Qu'une signature soit techniquement valide ne
 *     dit pas qu'un acte d'etat civil camerounais signe ainsi FAIT FOI. C'est
 *     la question A1 de docs/COMPLIANCE_OPEN_QUESTIONS.md, toujours ouverte.
 *     Tant qu'elle l'est, la mention « SANS VALEUR JURIDIQUE » reste (D-025).
 *
 *  2. **La residence des donnees.** Docusign est un service commercial
 *     etranger. Y faire transiter un acte d'etat civil — donc des donnees
 *     d'identite — appelle une decision explicite sur le lieu de traitement et
 *     sur ce que le prestataire conserve.
 *
 *  3. **Le compte et les habilitations.** Quel compte signe : la commune, le
 *     maire nominativement ? Un changement de maire doit-il invalider les
 *     actes anterieurs ? La reponse determine le modele d'habilitation.
 *
 * Cette classe LEVE une exception plutot que de rendre un document « signe ».
 * Un adaptateur non implemente qui renverrait un succes serait exactement le
 * chemin par lequel un acte frauduleux sort du systeme.
 */
final class DocusignSignatureProvider implements SignatureProvider
{
    public function sign(string $documentContents, array $context): SignatureResult
    {
        throw new RuntimeException(
            "L'adaptateur Docusign n'est pas implémenté. L'API est documentée, "
            ."mais trois questions restent ouvertes avant qu'un acte puisse être "
            ."signé par elle : la valeur juridique d'un acte d'état civil signé "
            .'électroniquement au Cameroun (question A1), le lieu de traitement '
            ."des données d'identité chez un prestataire étranger, et l'identité "
            .'du compte signataire. Voir docs/COMPLIANCE_OPEN_QUESTIONS.md. '
            .'En attendant : PHOENIX_SIGNATURE_PROVIDER=fake.'
        );
    }
}
