<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mayor;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Services\ActDraftService;
use App\Services\VerificationWorkflow;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Revue d'un dossier par le maire.
 *
 * « Optimise pour la décision rapide : l'essentiel du dossier visible sans
 * défilement » (8.2). L'écran présente donc d'abord ce qui décide — résultat
 * des 5 étapes, motif d'escalade s'il y en a un — puis le détail.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly VerificationWorkflow $workflow,
        private readonly ActDraftService $drafts,
    ) {}

    public function __invoke(Request $request, ReissuanceRequest $reissuanceRequest): View
    {
        $this->authorize('view', $reissuanceRequest);

        return view('mayor.review', [
            'demande' => $reissuanceRequest->load(
                'citizen.profile', 'attachments', 'center', 'commune',
                'decisions.actor', 'signature'
            ),
            'etapes' => $this->workflow->steps($reissuanceRequest),
            'steps' => VerificationWorkflow::STEPS,
            'complet' => $this->workflow->isComplete($reissuanceRequest),
            'manquantes' => $this->workflow->missingSteps($reissuanceRequest),
            'reservations' => $this->workflow->reservations($reissuanceRequest),
            'messages' => $reissuanceRequest->messages()->with('author:id,name')->oldest()->get(),
            'estEscaladee' => $reissuanceRequest->status === RequestStatus::Escalated,
            // Le projet que le maire va signer (D-068). Le meme que celui
            // qu'ActIssuanceService relira au moment de signer.
            'projet' => $this->drafts->latest($reissuanceRequest)?->load('officer:id,name'),
        ]);
    }
}
