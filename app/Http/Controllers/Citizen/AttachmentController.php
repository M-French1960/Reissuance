<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Models\RequestAttachment;
use App\Services\IdentityDocumentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly IdentityDocumentStore $store) {}

    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('update', $reissuanceRequest);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['selfie', 'id_document'])],
            'file' => [
                'required', 'file',
                // Le type est aussi relu depuis le contenu par le service :
                // l'en-tete envoye par le navigateur est sous controle du client.
                'mimetypes:'.implode(',', (array) config('phoenix.uploads.accepted_mime')),
                'max:'.(int) (config('phoenix.uploads.max_bytes') / 1024),
            ],
        ], [
            'file.mimetypes' => 'Le fichier doit être une image JPEG, PNG ou WebP.',
            'file.max' => 'Le fichier est trop volumineux. Reprenez la photo : elle sera compressée automatiquement.',
            'file.required' => "Aucun fichier n'a été reçu. Reprenez la photo puis réessayez.",
        ]);

        try {
            $this->store->store(
                $reissuanceRequest,
                $validated['file'],
                $validated['kind'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('status', 'La photo a été enregistrée.');
    }

    /**
     * Sert une piece d'identite.
     *
     * Aucune URL directe n'existe vers ces fichiers : ils vivent hors de
     * public/. La Policy est verifiee AVANT de servir le premier octet, et la
     * consultation est journalisee (tests R2 et R15 de docs/PERMISSIONS.md).
     */
    public function show(Request $request, RequestAttachment $attachment): StreamedResponse
    {
        $reissuanceRequest = ReissuanceRequest::loadForAuthorization($attachment->request_id);

        $this->authorize('viewIdentityDocuments', $reissuanceRequest);

        $contents = $this->store->read($attachment, $request->user());

        return response()->stream(
            fn () => print ($contents),
            200,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Length' => (string) strlen($contents),
                // Jamais mis en cache par un intermediaire : ce sont des
                // donnees d'identite.
                'Cache-Control' => 'no-store, private, max-age=0',
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
