<?php

declare(strict_types=1);

namespace App\Integrations\Fake;

use App\Contracts\SignatureProvider;
use App\Support\SignatureResult;
use Illuminate\Support\Str;

/**
 * Adaptateur factice de signature.
 *
 * Produit une preuve vérifiable en interne (HMAC sur l'empreinte du document)
 * et déclare explicitement `legallyBinding: false`.
 *
 * La mention « SANS VALEUR JURIDIQUE » est apposée sur le document lui-même
 * par DocumentBuilder, pas ici : un document signé par cet adaptateur doit
 * porter la mention même si quelqu'un contourne cette classe.
 */
final class FakeSignatureProvider implements SignatureProvider
{
    public const PROVIDER = 'fake-signature';

    public function sign(string $documentContents, array $context): SignatureResult
    {
        $hash = hash('sha256', $documentContents);

        return new SignatureResult(
            signedDocument: $documentContents,
            documentHash: $hash,
            provider: self::PROVIDER,
            proof: [
                'algorithm' => 'HMAC-SHA256 (démonstration)',
                // Scellé avec APP_KEY : vérifiable par cette installation, et
                // par elle seule. Ce n'est pas une signature au sens juridique.
                'seal' => hash_hmac('sha256', $hash, (string) config('app.key')),
                'signed_at' => now()->toIso8601String(),
                'signature_reference' => (string) Str::uuid(),
                'signatory' => $context['signatory'] ?? null,
                'commune' => $context['commune'] ?? null,
                'request_reference' => $context['reference'] ?? null,
                'legally_binding' => false,
                'notice' => 'Signature de démonstration. Sans valeur juridique. '
                    .'La question A1 de docs/COMPLIANCE_OPEN_QUESTIONS.md est ouverte.',
            ],
            legallyBinding: false,
        );
    }
}
