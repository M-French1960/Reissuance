<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Résultat d'une signature.
 *
 * `documentHash` est enregistré en base (`document_signatures.document_hash`)
 * et ne dépend d'aucun prestataire : il permettra de prouver plus tard qu'un
 * acte présenté est bien celui qui a été signé, quel que soit le choix final
 * de prestataire.
 */
final readonly class SignatureResult
{
    /** @param  array<string, mixed>  $proof */
    public function __construct(
        public string $signedDocument,
        public string $documentHash,
        public string $provider,
        public array $proof,
        public bool $legallyBinding,
    ) {}
}
