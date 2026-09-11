<?php

declare(strict_types=1);

namespace App\Integrations\Real\Docusign;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client HTTP de l'API eSignature de Docusign.
 *
 * SOURCE DU CONTRAT — et c'est le point important : rien ici ne vient de
 * memoire. Les chemins, les noms de champs et la forme de l'assertion JWT ont
 * ete releves dans le code publie par Docusign lui-meme :
 *
 *   - docusign/docusign-esign-php-client (SDK officiel)
 *       src/Client/ApiClient.php          : /oauth/token, /oauth/userinfo,
 *                                           claim JWT {iss, sub, aud, iat,
 *                                           exp, scope}, RS256,
 *                                           grant_type urn:ietf:params:oauth:
 *                                           grant-type:jwt-bearer
 *       src/Client/Auth/Account.php       : {account_id, is_default, base_uri}
 *       src/Api/EnvelopesApi.php          : /v2.1/accounts/{id}/envelopes[...]
 *       src/Api/TrustServiceProvidersApi  : /v2.1/accounts/{id}/seals
 *       src/Model/Recipients.php          : recipients.seals => SealSign[]
 *   - docusign/code-examples-php
 *       src/Services/JWTService.php       : base_uri . "/restapi",
 *                                           erreur « consent_required »
 *
 * AUCUN APPEL N'A ETE FAIT CONTRE LE SERVICE REEL. Sans identifiants, ce
 * client n'est verifie que contre un serveur simule — meme reserve que pour
 * HR-Skills Pay (D-050). Il reste a reprendre contre le bac a sable Docusign.
 *
 * Ce que ce client NE FAIT PAS : il n'enveloppe aucune logique metier de
 * PHOENIX. Il ne decide pas qu'un acte peut etre signe, il ne juge pas de sa
 * valeur juridique. Voir DocusignSignatureProvider pour ce qui bloque encore.
 */
final class DocusignClient
{
    /**
     * Portees demandees.
     *
     * `signature` pour l'API eSignature, `impersonation` parce que le flux
     * JWT Grant agit AU NOM d'un utilisateur (le compte signataire), et non
     * au nom de l'application.
     */
    private const SCOPES = 'signature impersonation';

    /** Docusign plafonne la validite de l'assertion a 60 minutes. */
    private const ASSERTION_LIFETIME_SECONDS = 3600;

    /**
     * Marge retranchee a la duree du jeton avant de le redemander.
     *
     * Un jeton qui expire pendant l'appel produit un 401 sur une operation
     * qui, elle, a peut-etre deja eu lieu cote Docusign.
     */
    private const TOKEN_MARGIN_SECONDS = 120;

    /* ------------------------------------------------------------------ */
    /* Authentification */
    /* ------------------------------------------------------------------ */

    /**
     * Jeton d'acces, obtenu par JWT Grant et garde en cache.
     *
     * Le jeton est un secret porteur : il n'est jamais journalise, et le
     * corps de la reponse d'erreur ne l'est pas non plus.
     */
    public function accessToken(): string
    {
        $cle = 'phoenix:docusign:access-token';

        $jeton = Cache::get($cle);

        if (is_string($jeton) && $jeton !== '') {
            return $jeton;
        }

        $reponse = $this->request()
            ->asForm()
            ->post($this->oauthUrl('/oauth/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion(),
            ]);

        if ($reponse->failed()) {
            throw $this->authenticationFailure($reponse);
        }

        $jeton = $reponse->json('access_token');
        $duree = (int) ($reponse->json('expires_in') ?? 0);

        if (! is_string($jeton) || $jeton === '') {
            throw new RuntimeException(
                'Docusign a répondu sans jeton d’accès. Vérifiez la clé d’intégration '
                .'et l’utilisateur impersonné.'
            );
        }

        // Si Docusign ne dit pas combien de temps le jeton vaut, on ne le
        // garde pas : mieux vaut un aller-retour de plus qu'un jeton expire
        // au milieu d'une signature.
        if ($duree > self::TOKEN_MARGIN_SECONDS) {
            Cache::put($cle, $jeton, $duree - self::TOKEN_MARGIN_SECONDS);
        }

        return $jeton;
    }

    /**
     * Le compte et son domaine d'hebergement, lus dans /oauth/userinfo.
     *
     * `account_id` peut etre force par configuration : un utilisateur Docusign
     * peut appartenir a plusieurs comptes, et « le compte par defaut » n'est
     * pas une garantie suffisante pour une signature d'acte d'etat civil.
     */
    public function account(): DocusignAccount
    {
        $reponse = $this->request()
            ->withToken($this->accessToken())
            ->acceptJson()
            ->get($this->oauthUrl('/oauth/userinfo'));

        if ($reponse->failed()) {
            throw new RuntimeException(
                "Docusign a refusé la lecture du compte (HTTP {$reponse->status()})."
            );
        }

        /** @var list<array<string, mixed>> $comptes */
        $comptes = (array) ($reponse->json('accounts') ?? []);

        if ($comptes === []) {
            throw new RuntimeException(
                "L'utilisateur Docusign impersonné n'est rattaché à aucun compte."
            );
        }

        $voulu = trim((string) config('phoenix.signature.docusign.account_id'));

        $choisi = null;

        foreach ($comptes as $compte) {
            $id = (string) ($compte['account_id'] ?? '');

            if ($voulu !== '') {
                if ($id === $voulu) {
                    $choisi = $compte;
                    break;
                }

                continue;
            }

            // `is_default` arrive en chaîne « true » dans le contrat publié.
            if (filter_var($compte['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $choisi = $compte;
                break;
            }
        }

        if ($choisi === null) {
            throw new RuntimeException(
                $voulu !== ''
                    ? "Le compte Docusign {$voulu} n'est pas accessible à l'utilisateur impersonné."
                    : 'Aucun compte Docusign par défaut. Fixez PHOENIX_DOCUSIGN_ACCOUNT_ID : '
                      .'le compte signataire d’un acte ne se devine pas.'
            );
        }

        $base = (string) ($choisi['base_uri'] ?? '');

        if ($base === '') {
            throw new RuntimeException(
                "Docusign n'a pas indiqué le domaine du compte (base_uri). "
                .'Sans lui, on ignore dans quelle région les données seraient traitées.'
            );
        }

        return new DocusignAccount((string) $choisi['account_id'], $base);
    }

    /* ------------------------------------------------------------------ */
    /* Sceaux électroniques */
    /* ------------------------------------------------------------------ */

    /**
     * Les sceaux electroniques disponibles sur le compte.
     *
     * Un sceau est la SEULE facon de faire apposer une signature par la
     * plateforme sans intervention humaine dans l'interface Docusign. Il doit
     * etre provisionne sur le compte : il ne se cree pas par l'API.
     *
     * On rend `seal_name` et non `seal_display_name` : dans l'exemple publie
     * par Docusign, la valeur attendue dans l'enveloppe est un identifiant
     * (un GUID), pas le libelle affiche a l'utilisateur.
     *
     * ATTENTION AUX DEUX CONVENTIONS. Docusign n'en emploie pas qu'une :
     * /oauth/userinfo rend `account_id` et `base_uri` en minuscules souligne,
     * tandis que l'API eSignature rend `sealName`, `envelopeId` en casse
     * chamelle. L'`attributeMap` du SDK officiel le dit champ par champ, et
     * c'est lui qui fait foi ici — pas l'habitude.
     *
     * @return list<string> les noms de sceau (`sealName`)
     */
    public function availableSeals(): array
    {
        $compte = $this->account();

        $reponse = $this->request()
            ->withToken($this->accessToken())
            ->acceptJson()
            ->get("{$compte->restApi()}/v2.1/accounts/{$compte->accountId}/seals");

        if ($reponse->failed()) {
            throw new RuntimeException(
                "Docusign a refusé la liste des sceaux (HTTP {$reponse->status()}). "
                .'Le sceau électronique est une option de compte : sans lui, aucune '
                .'signature ne peut être apposée côté serveur.'
            );
        }

        $noms = [];

        foreach ((array) ($reponse->json('seals') ?? []) as $sceau) {
            $nom = is_array($sceau) ? ($sceau['sealName'] ?? null) : null;

            if (is_string($nom) && $nom !== '') {
                $noms[] = $nom;
            }
        }

        return $noms;
    }

    /* ------------------------------------------------------------------ */
    /* Enveloppes */
    /* ------------------------------------------------------------------ */

    /**
     * Cree une enveloppe et rend son identifiant.
     *
     * @param  array<string, mixed>  $definition
     */
    public function createEnvelope(array $definition): string
    {
        $compte = $this->account();

        $reponse = $this->request()
            ->withToken($this->accessToken())
            ->acceptJson()
            ->post("{$compte->restApi()}/v2.1/accounts/{$compte->accountId}/envelopes", $definition);

        if ($reponse->failed()) {
            throw new RuntimeException(
                "Docusign a refusé la création de l'enveloppe (HTTP {$reponse->status()}) : "
                .$this->errorMessage($reponse)
            );
        }

        $id = $reponse->json('envelopeId');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException(
                "Docusign a accepté l'enveloppe sans rendre son identifiant. "
                ."L'enveloppe existe peut-être : ne pas réessayer à l'aveugle."
            );
        }

        return $id;
    }

    /** Statut d'une enveloppe : `sent`, `delivered`, `completed`, `declined`, `voided`. */
    public function envelopeStatus(string $envelopeId): string
    {
        $compte = $this->account();

        $reponse = $this->request()
            ->withToken($this->accessToken())
            ->acceptJson()
            ->get("{$compte->restApi()}/v2.1/accounts/{$compte->accountId}/envelopes/"
                .rawurlencode($envelopeId));

        if ($reponse->failed()) {
            throw new RuntimeException(
                "Docusign a refusé la lecture de l'enveloppe (HTTP {$reponse->status()})."
            );
        }

        $statut = $reponse->json('status');

        if (! is_string($statut) || $statut === '') {
            throw new RuntimeException(
                "Docusign a rendu une enveloppe sans statut. Un statut inconnu n'est "
                .'jamais interprété comme « signé ».'
            );
        }

        return strtolower($statut);
    }

    /**
     * Telecharge un document de l'enveloppe.
     *
     * `combined` rend l'ensemble des documents signes en un seul PDF ; `1`
     * rend le premier document. On ne suppose pas le type MIME : on verifie
     * que ce qui revient est bien un PDF, parce qu'un corps d'erreur JSON
     * rendu avec un code 200 serait sinon enregistre comme un acte.
     */
    public function downloadDocument(string $envelopeId, string $documentId = 'combined'): string
    {
        $compte = $this->account();

        $reponse = $this->request()
            ->withToken($this->accessToken())
            ->get("{$compte->restApi()}/v2.1/accounts/{$compte->accountId}/envelopes/"
                .rawurlencode($envelopeId).'/documents/'.rawurlencode($documentId));

        if ($reponse->failed()) {
            throw new RuntimeException(
                "Docusign a refusé le téléchargement du document (HTTP {$reponse->status()})."
            );
        }

        $contenu = $reponse->body();

        if (! str_starts_with($contenu, '%PDF-')) {
            throw new RuntimeException(
                "Docusign a rendu un contenu qui n'est pas un PDF. Aucun acte n'est "
                .'enregistré à partir d’une réponse dont la nature est incertaine.'
            );
        }

        return $contenu;
    }

    /* ------------------------------------------------------------------ */
    /* Format de l'enveloppe */
    /* ------------------------------------------------------------------ */

    /**
     * Definition d'une enveloppe scellee — sans destinataire humain.
     *
     * `status: sent` demande a Docusign de traiter l'enveloppe immediatement.
     * Le sceau est un destinataire de type `seals` : il n'a ni adresse
     * electronique ni action a accomplir, la plateforme l'appose elle-meme.
     *
     * CE QUI N'EST PAS FAIT ICI, volontairement : aucun `signers`. Un
     * destinataire humain rendrait la signature dependante d'un clic dans
     * l'interface de Docusign, hors du systeme et hors de toute tracabilite
     * PHOENIX.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sealedEnvelopeDefinition(
        string $pdf,
        string $sealName,
        array $context,
    ): array {
        $reference = (string) ($context['reference'] ?? '');

        return [
            'emailSubject' => "Acte d'état civil – {$reference}",
            'status' => 'sent',
            'documents' => [[
                'documentBase64' => base64_encode($pdf),
                'name' => "acte-{$reference}.pdf",
                'fileExtension' => 'pdf',
                'documentId' => '1',
            ]],
            'recipients' => [
                // Forme reprise TELLE QUELLE de l'exemple publie par Docusign
                // dans src/Model/SealSign.php du SDK officiel — y compris le
                // melange chaine / entier, qu'on ne « corrige » pas : c'est le
                // seul exemple de sceau que le prestataire publie.
                'seals' => [[
                    'recipientId' => '1',
                    'routingOrder' => 1,
                    'recipientSignatureProviders' => [[
                        'sealName' => $sealName,
                    ]],
                ]],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Assertion JWT */
    /* ------------------------------------------------------------------ */

    /**
     * L'assertion RS256 exigee par le flux JWT Grant.
     *
     * Signee ici avec openssl, sans dependance supplementaire : l'algorithme
     * et la forme du claim sont ceux du SDK officiel.
     */
    private function assertion(): string
    {
        $maintenant = time();

        $claim = [
            'iss' => $this->requiredConfig('integration_key', 'PHOENIX_DOCUSIGN_INTEGRATION_KEY'),
            'sub' => $this->requiredConfig('user_id', 'PHOENIX_DOCUSIGN_USER_ID'),
            'aud' => $this->oauthHost(),
            'iat' => $maintenant,
            'exp' => $maintenant + self::ASSERTION_LIFETIME_SECONDS,
            'scope' => self::SCOPES,
        ];

        $entree = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR))
            .'.'.$this->base64Url(json_encode($claim, JSON_THROW_ON_ERROR));

        $cle = openssl_pkey_get_private($this->privateKey());

        if ($cle === false) {
            throw new RuntimeException(
                'La clé privée Docusign est illisible. Attendue : une clé RSA au format PEM.'
            );
        }

        $signature = '';

        if (! openssl_sign($entree, $signature, $cle, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException("La signature de l'assertion Docusign a échoué.");
        }

        return $entree.'.'.$this->base64Url($signature);
    }

    /**
     * La cle privee, lue depuis un fichier hors du depot ou depuis une
     * variable d'environnement contenant le PEM.
     *
     * REFUS DELIBERE : une cle rangee dans l'arborescence du projet est
     * refusee. C'est la cle qui autorise a signer des actes ; elle n'a rien a
     * faire a portee d'un `git add`, ni d'une erreur de configuration du
     * serveur web.
     */
    private function privateKey(): string
    {
        $valeur = trim((string) config('phoenix.signature.docusign.private_key'));

        if ($valeur === '') {
            throw new RuntimeException(
                'PHOENIX_DOCUSIGN_PRIVATE_KEY est vide. Indiquez le chemin du fichier de '
                .'clé privée RSA, hors du dépôt.'
            );
        }

        if (str_starts_with($valeur, '-----BEGIN')) {
            return $valeur;
        }

        $chemin = realpath($valeur);

        if ($chemin === false || ! is_readable($chemin)) {
            throw new RuntimeException(
                'La clé privée Docusign est introuvable ou illisible au chemin indiqué.'
            );
        }

        $projet = realpath(base_path());

        if ($projet !== false && str_starts_with($chemin, $projet.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(
                'La clé privée Docusign est rangée dans le dépôt du projet. '
                .'Déplacez-la hors de l’arborescence : cette clé autorise à signer des actes.'
            );
        }

        return (string) file_get_contents($chemin);
    }

    /* ------------------------------------------------------------------ */
    /* Plomberie */
    /* ------------------------------------------------------------------ */

    private function request(): PendingRequest
    {
        // Pas de ->throw() : chaque appel examine lui-meme le code HTTP et
        // rend un message utile. Une exception de transport generique dirait
        // « 401 » la ou il faut dire « le consentement n'a pas ete accorde ».
        return Http::timeout((int) config('phoenix.signature.docusign.timeout', 30));
    }

    private function oauthHost(): string
    {
        $hote = trim((string) config('phoenix.signature.docusign.oauth_base_path'));

        if ($hote === '') {
            throw new RuntimeException(
                'PHOENIX_DOCUSIGN_OAUTH_BASE est vide. Attendu : account-d.docusign.com '
                .'(bac à sable) ou account.docusign.com (production).'
            );
        }

        // Un hote nu : `aud` doit valoir exactement ce que Docusign attend,
        // sans schéma ni barre finale.
        return rtrim(preg_replace('#^https?://#', '', $hote) ?? $hote, '/');
    }

    private function oauthUrl(string $chemin): string
    {
        return 'https://'.$this->oauthHost().$chemin;
    }

    private function requiredConfig(string $cle, string $variable): string
    {
        $valeur = trim((string) config("phoenix.signature.docusign.{$cle}"));

        if ($valeur === '') {
            throw new RuntimeException("{$variable} est vide : Docusign n'est pas configuré.");
        }

        return $valeur;
    }

    /**
     * L'echec d'authentification, traduit.
     *
     * `consent_required` est le cas de premiere mise en service : le flux JWT
     * Grant exige que l'utilisateur impersonne ait accorde son consentement
     * une fois, dans un navigateur. Ce n'est pas une panne, et le dire evite
     * de chercher une erreur de cle la ou il n'y en a pas.
     */
    private function authenticationFailure(Response $reponse): RuntimeException
    {
        $erreur = (string) ($reponse->json('error') ?? '');

        if ($erreur === 'consent_required') {
            return new RuntimeException(
                "Docusign exige le consentement de l'utilisateur impersonné. Il s'accorde "
                .'une seule fois, dans un navigateur, à l’adresse '
                ."https://{$this->oauthHost()}/oauth/auth avec response_type=code, "
                .'scope=signature impersonation et la clé d’intégration de cette installation.'
            );
        }

        // On ne recopie PAS le corps : il peut contenir l'assertion renvoyée
        // en écho, donc une signature valide de notre clé privée.
        return new RuntimeException(
            "Docusign a refusé l'authentification (HTTP {$reponse->status()}"
            .($erreur !== '' ? ", {$erreur}" : '').').'
        );
    }

    private function errorMessage(Response $reponse): string
    {
        $message = $reponse->json('message');

        return is_string($message) && $message !== '' ? $message : 'sans message.';
    }

    private function base64Url(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }
}
