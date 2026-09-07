<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\SignatureProvider;
use App\Support\SignatureResult;
use RuntimeException;

/**
 * Squelette de l'adaptateur réel de signature.
 *
 * Lève une exception explicite : un adaptateur non implémenté qui renverrait
 * un document « signé » serait exactement le chemin par lequel un acte
 * frauduleux sort du système.
 */
final class AccreditedSignatureProvider implements SignatureProvider
{
    public function sign(string $documentContents, array $context): SignatureResult
    {
        throw new RuntimeException(
            "L'adaptateur réel de signature électronique n'est pas implémenté. "
            ."Aucun prestataire agréé n'a été identifié, et la valeur légale d'un acte "
            ."d'état civil signé électroniquement au Cameroun reste à confirmer "
            .'(questions A1 à A4 de docs/COMPLIANCE_OPEN_QUESTIONS.md). '
            .'Utilisez PHOENIX_SIGNATURE_PROVIDER=fake.'
        );
    }
}
