<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\FacialRecognitionProvider;
use App\Models\RequestAttachment;
use App\Support\ProviderResponse;
use RuntimeException;

/**
 * Squelette d'une comparaison faciale reelle.
 *
 * IL N'EXISTE PAS. Aucun fournisseur n'est documente, et les questions
 * d'encadrement de docs/BIOMETRIE.md restent ouvertes.
 *
 * Cette classe leve une exception explicite plutot que de rendre un « match »
 * simule. Un avis biometrique fabrique conduirait un officier a delivrer un
 * acte en croyant qu'une machine a confirme l'identite.
 */
final class BiometricFacialRecognitionProvider implements FacialRecognitionProvider
{
    public function compare(RequestAttachment $selfie, RequestAttachment $idDocument): ProviderResponse
    {
        throw new RuntimeException(
            "Aucun service de comparaison faciale n'est branché. Le contrat, le "
            .'prestataire, la conservation des données et le recours en cas de '
            .'non-reconnaissance restent à définir : voir docs/BIOMETRIE.md.'
        );
    }
}
