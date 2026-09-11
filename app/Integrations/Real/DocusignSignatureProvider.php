<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\SignatureProvider;
use App\Integrations\Real\Docusign\DocusignClient;
use App\Support\SignatureResult;
use RuntimeException;

/**
 * Adaptateur Docusign — l'acteur « Docusign API » du diagramme de cas
 * d'utilisation.
 *
 * LE CLIENT EST CONSTRUIT. App\Integrations\Real\Docusign\DocusignClient
 * implemente le flux complet, releve dans le code publie par Docusign :
 * authentification JWT Grant, lecture du compte et de son domaine
 * d'hebergement, liste des sceaux, creation d'enveloppe, statut, telechargement
 * du document signe. Il est verifie contre un serveur simule.
 *
 * CE QUI EMPECHE MALGRE TOUT DE SIGNER — et il faut le dire dans cet ordre,
 * parce que le premier point est technique et se corrige, tandis que les
 * suivants ne relevent pas de l'ingenierie :
 *
 *  1. **Le contrat est synchrone, Docusign ne l'est pas.**
 *     `sign()` recoit un PDF et doit rendre un PDF signe dans le meme appel.
 *     Docusign travaille par enveloppes : on en cree une, la plateforme la
 *     traite, puis on recupere le document. Meme avec un sceau electronique —
 *     le seul mode sans intervention humaine — il faut creer, attendre, puis
 *     telecharger.
 *
 *     Aggravant, et verifie : ActIssuanceService::issue() appelle `sign()`
 *     A L'INTERIEUR d'un DB::transaction(). Attendre Docusign ici tiendrait
 *     une transaction MySQL ouverte, verrous compris, pendant un appel reseau
 *     vers un service etranger — sur les reseaux contraints vises par le
 *     projet, c'est une panne, pas un ralentissement.
 *
 *     Ce que cela impose : un flux en deux temps. La decision du maire
 *     DEMANDE la signature et place la demande dans un etat d'attente ; un
 *     travail de file recupere l'acte signe et acheve la transition. C'est un
 *     changement de machine a etats, sur le chemin le plus sensible du
 *     systeme. Il ne sera pas fait a l'aveugle, avant le point 2.
 *
 *  2. **La valeur juridique.** Qu'une signature soit techniquement valide ne
 *     dit pas qu'un acte d'etat civil camerounais signe ainsi FAIT FOI. C'est
 *     la question A1 de docs/COMPLIANCE_OPEN_QUESTIONS.md, toujours ouverte.
 *     Tant qu'elle l'est, la mention « SANS VALEUR JURIDIQUE » reste (D-025),
 *     et construire la machine a etats du point 1 reviendrait a batir le
 *     chemin de production d'actes autour d'un prestataire dont on ignore
 *     s'il peut legalement servir.
 *
 *  3. **La residence des donnees.** Docusign heberge chaque compte dans une
 *     region donnee ; `DocusignClient::account()` rend ce domaine, ce qui
 *     rend la question verifiable — mais ne la tranche pas. Faire transiter
 *     un acte d'etat civil, donc des donnees d'identite, par un prestataire
 *     etranger appelle une decision explicite.
 *
 *  4. **Le compte signataire.** Le flux JWT Grant agit AU NOM d'un
 *     utilisateur : c'est lui qui apparait comme expediteur de l'enveloppe.
 *     La commune ? Le maire nominativement ? Un changement de maire doit-il
 *     invalider les actes anterieurs ? La reponse determine le modele
 *     d'habilitation, et elle n'est pas d'ordre technique.
 *
 * Cette classe LEVE, et elle leve AVANT le moindre appel : aucune enveloppe
 * n'est creee en passant. Un adaptateur non acheve qui renverrait un succes
 * serait exactement le chemin par lequel un acte frauduleux sort du systeme.
 */
final class DocusignSignatureProvider implements SignatureProvider
{
    public const PROVIDER = 'docusign';

    public function __construct(private readonly DocusignClient $client) {}

    public function sign(string $documentContents, array $context): SignatureResult
    {
        throw new RuntimeException(
            "La signature par Docusign n'est pas active. Le client est construit et vérifié "
            .'contre un serveur simulé, mais quatre obstacles subsistent. Le premier est '
            .'technique : Docusign signe de façon asynchrone — on crée une enveloppe, la '
            ."plateforme la traite, puis on récupère l'acte — alors que ce contrat exige un "
            ."document signé dans le même appel, appel lui-même effectué à l'intérieur d'une "
            .'transaction de base de données. Y attendre un service étranger tiendrait des '
            .'verrous ouverts pendant tout le délai. Les trois autres ne sont pas techniques : '
            ."la valeur juridique d'un acte d'état civil signé électroniquement au Cameroun "
            .'(question A1), le lieu de traitement des données d’identité, et l’identité du '
            .'compte signataire. Voir docs/COMPLIANCE_OPEN_QUESTIONS.md et D-066. '
            .'En attendant : PHOENIX_SIGNATURE_PROVIDER=fake.'
        );
    }

    /**
     * Le client, pour le diagnostic et pour la reprise contre le bac a sable.
     *
     * Expose deliberement : le jour ou le flux en deux temps sera construit,
     * c'est lui qui sera appele — et d'ici la, il permet de verifier une
     * configuration Docusign sans passer par la production d'un acte.
     */
    public function client(): DocusignClient
    {
        return $this->client;
    }
}
