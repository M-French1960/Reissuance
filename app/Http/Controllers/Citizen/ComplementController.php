<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Services\ComplementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Le demandeur repond a une piece reclamee (D-087).
 *
 * LA PORTE N'EXISTE QUE SI ELLE A ETE OUVERTE. La Policy exige un complement
 * encore en attente : sans demande d'un officier, ces deux routes repondent
 * 403. C'est ce qui empeche quelqu'un de remplacer, apres coup, une piece
 * d'identite deja verifiee par une autre.
 *
 * CE QUE L'ECRAN NE MONTRE PAS : l'ancienne photo a cote de la nouvelle,
 * comme le proposait la maquette. Le magasin de pieces remplace la piece du
 * meme type et SUPPRIME la precedente — une piece d'identite refusee n'a pas a
 * etre conservee pour la montrer. La comparaison cote a cote est donc
 * impossible, et c'est le bon arbitrage.
 */
class ComplementController extends Controller
{
    public function __construct(private readonly ComplementService $complements) {}

    public function show(Request $request, ReissuanceRequest $reissuanceRequest): View
    {
        $this->authorize('provideComplement', $reissuanceRequest);

        return view('citizen.complement', [
            'demande' => $reissuanceRequest,
            'complement' => $reissuanceRequest->pendingComplement()->firstOrFail(),
        ]);
    }

    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('provideComplement', $reissuanceRequest);

        $valide = $request->validate([
            'file' => [
                'required', 'file',
                // Le type est aussi relu depuis le CONTENU par le magasin :
                // l'en-tete envoye par le navigateur est sous controle du
                // client, et ne prouve rien.
                'mimetypes:'.implode(',', (array) config('phoenix.uploads.accepted_mime')),
                'max:'.(int) (config('phoenix.uploads.max_bytes') / 1024),
            ],
        ], [
            'file.mimetypes' => __('flash.citizen.file_mimetypes'),
            'file.max' => __('flash.citizen.file_max'),
            'file.required' => __('flash.citizen.file_required'),
        ]);

        try {
            $this->complements->fulfil(
                $reissuanceRequest->pendingComplement()->firstOrFail(),
                $valide['file'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('citizen.requests.show', $reissuanceRequest)
            ->with('status', __('flash.citizen.complement_sent'));
    }
}
