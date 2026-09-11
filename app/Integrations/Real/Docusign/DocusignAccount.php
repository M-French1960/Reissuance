<?php

declare(strict_types=1);

namespace App\Integrations\Real\Docusign;

/**
 * Le compte Docusign sur lequel travaille cette installation.
 *
 * `baseUri` n'est PAS une constante : Docusign heberge chaque compte sur un
 * domaine qui depend de sa region (demo.docusign.net, eu.docusign.net, ...).
 * Il se lit dans /oauth/userinfo, il ne se devine pas — et c'est aussi ce qui
 * rend verifiable la question de la residence des donnees : le domaine rendu
 * dit ou le compte est heberge.
 */
final readonly class DocusignAccount
{
    public function __construct(
        public string $accountId,
        public string $baseUri,
    ) {}

    /** La racine de l'API eSignature : le SDK officiel suffixe `/restapi`. */
    public function restApi(): string
    {
        return rtrim($this->baseUri, '/').'/restapi';
    }
}
