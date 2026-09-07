<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service de l'acte signé et de sa preuve.
 *
 * Comme les pièces d'identité : hors de public/, Policy vérifiée AVANT de
 * servir le premier octet, consultation journalisée.
 */
class ActDocumentController extends Controller
{
    public function document(Request $request, DocumentSignature $signature): StreamedResponse
    {
        return $this->serve($request, $signature, $signature->document_path, 'document', 'acte.pdf');
    }

    public function proof(Request $request, DocumentSignature $signature): StreamedResponse
    {
        return $this->serve($request, $signature, $signature->proof_path, 'proof', 'preuve-de-signature.pdf');
    }

    private function serve(
        Request $request,
        DocumentSignature $signature,
        ?string $path,
        string $type,
        string $filename,
    ): StreamedResponse {
        $demande = ReissuanceRequest::withoutGlobalScopes()->findOrFail($signature->request_id);

        // 404 et non 403 : les identifiants de signature sont sequentiels, et
        // un 403 confirmerait qu'un acte porte ce numero — donc combien
        // d'actes ont ete delivres. Le refus ne doit rien apprendre.
        abort_unless($request->user()?->can('view', $demande), 404);

        abort_if($path === null || ! Storage::disk('private')->exists($path), 404);

        $contents = (string) Storage::disk('private')->get($path);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role->value,
            'action' => "act.{$type}_downloaded",
            'auditable_type' => 'document_signature',
            'auditable_id' => $signature->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->stream(
            fn () => print ($contents),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($contents),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-store, private, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
