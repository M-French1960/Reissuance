<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Services\ComplementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * L'officier reclame une piece au demandeur (D-087).
 *
 * POURQUOI CETTE PORTE EXISTE. Jusqu'ici, une photo floue condamnait le
 * dossier : le demandeur ne pouvait plus toucher a ses pieces une fois la
 * demande envoyee, et l'officier n'avait d'autre choix que de rejeter
 * quelqu'un de bonne foi pour un reflet sur une carte. « Aucune impasse »
 * (8.1 du brief) : il y a maintenant une sortie.
 *
 * LE MOTIF EST OBLIGATOIRE ET DOIT ETRE UTILISABLE. Dix caracteres au
 * minimum, impose aussi par la base. « Photo illisible » ne dit pas quoi
 * refaire ; le demandeur renverrait la meme photo, et la boucle recommencerait
 * a ses frais.
 */
class ComplementController extends Controller
{
    public function __construct(private readonly ComplementService $complements) {}

    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('requestComplement', $reissuanceRequest);

        $valide = $request->validate([
            'kind' => ['required', Rule::in(['id_document', 'selfie'])],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'message.min' => __('flash.officer.complement_message_min'),
        ]);

        try {
            $this->complements->open(
                $reissuanceRequest,
                $request->user(),
                $valide['kind'],
                trim($valide['message']),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        return back()->with('status', __('flash.officer.complement_requested'));
    }
}
