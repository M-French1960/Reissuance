<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mayor;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use App\Services\ActIssuanceService;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Décisions du maire : T7, T8, T9, T10, T11 de docs/STATE_MACHINE.md.
 *
 * | Action                  | Depuis              | Vers               | Motif |
 * |-------------------------|---------------------|--------------------|-------|
 * | signer (T7)             | awaiting_signature  | signed             | non   |
 * | retourner (T8)          | awaiting_signature  | under_review       | OUI   |
 * | approuver par exc. (T9) | escalated           | signed             | OUI   |
 * | rejeter (T10)           | escalated           | rejected           | OUI   |
 * | retourner (T11)         | escalated           | under_review       | OUI   |
 */
class DecisionController extends Controller
{
    public function __construct(
        private readonly ActIssuanceService $issuance,
        private readonly RequestTransitionService $transitions,
        private readonly VerificationWorkflow $workflow,
    ) {}

    /** T7 et T9 : la signature produit l'acte. */
    public function sign(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('sign', $reissuanceRequest);

        $estEscaladee = $reissuanceRequest->status === RequestStatus::Escalated;

        $validated = $request->validate([
            // T9 « approuver par exception » exige un motif : le maire passe
            // outre une escalade, cela doit être justifié et tracé.
            'reason' => $estEscaladee
                ? ['required', 'string', 'min:10', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => "Approuver par exception une demande escaladée exige un motif : il figurera au dossier et au journal d'audit.",
            'reason.min' => 'Le motif doit être suffisamment explicite : au moins 10 caractères.',
        ]);

        // Barrière du §4.3 : un acte signé exige une vérification complète.
        // Elle s'applique aussi au maire — y compris pour une approbation par
        // exception, qui passe outre le JUGEMENT de l'officier, pas outre la
        // vérification elle-même.
        if (! $this->workflow->isComplete($reissuanceRequest)) {
            $libelles = array_map(
                fn (int $n): string => "{$n}. ".VerificationWorkflow::STEPS[$n],
                $this->workflow->missingSteps($reissuanceRequest)
            );

            return back()->withErrors([
                'reason' => 'Cette demande ne peut pas être signée : la vérification est incomplète. Il manque — '
                    .implode(' ; ', $libelles)
                    .". Retournez le dossier à l'officier.",
            ])->withInput();
        }

        $depuis = $reissuanceRequest->status;

        try {
            $signature = DB::transaction(function () use ($request, $reissuanceRequest, $validated, $depuis, $estEscaladee) {
                $signature = $this->issuance->issue(
                    $reissuanceRequest, $request->user(), $validated['reason'] ?? null
                );

                RequestDecision::create([
                    'request_id' => $reissuanceRequest->id,
                    'actor_id' => $request->user()->id,
                    'actor_role' => $request->user()->role->value,
                    'decision' => $estEscaladee
                        ? DecisionType::ApprovedByException->value
                        : DecisionType::Signed->value,
                    'reason' => $validated['reason'] ?? null,
                    'from_status' => $depuis->value,
                    'to_status' => RequestStatus::Signed->value,
                ]);

                return $signature;
            });
        } catch (DomainException|RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        $avis = $signature->legally_binding
            ? ''
            : ' Attention : le document produit porte la mention « sans valeur juridique ».';

        return redirect()->route('mayor.dashboard')->with(
            'status',
            "Acte délivré pour la demande {$reissuanceRequest->reference}.".$avis
        );
    }

    /** T10 : rejet d'une demande escaladée. */
    public function reject(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('sign', $reissuanceRequest);

        abort_unless($reissuanceRequest->status === RequestStatus::Escalated, 422);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Un motif de rejet est obligatoire : il sera communiqué au citoyen.',
        ]);

        $this->applyDecision(
            $request, $reissuanceRequest, RequestStatus::Rejected,
            DecisionType::Rejected, $validated['reason']
        );

        return redirect()->route('mayor.dashboard')->with(
            'status', "Demande {$reissuanceRequest->reference} rejetée."
        );
    }

    /**
     * T8 et T11 : retour à l'officier.
     *
     * Ouvre un nouveau cycle de vérification : les étapes de la passe
     * précédente sont conservées intactes (docs/STATE_MACHINE.md 3.3).
     */
    public function returnToOfficer(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('returnToOfficer', $reissuanceRequest);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => "Indiquez à l'officier ce qui doit être repris : ce motif est sa seule consigne.",
        ]);

        DB::transaction(function () use ($request, $reissuanceRequest, $validated): void {
            $this->applyDecision(
                $request, $reissuanceRequest, RequestStatus::UnderReview,
                DecisionType::Returned, $validated['reason']
            );

            $this->workflow->openNewCycle($reissuanceRequest->refresh());
        });

        return redirect()->route('mayor.dashboard')->with(
            'status',
            "Demande {$reissuanceRequest->reference} retournée à l'officier pour une nouvelle vérification."
        );
    }

    private function applyDecision(
        Request $request,
        ReissuanceRequest $demande,
        RequestStatus $cible,
        DecisionType $decision,
        string $reason,
    ): void {
        $depuis = $demande->status;

        DB::transaction(function () use ($request, $demande, $cible, $decision, $reason, $depuis): void {
            $this->transitions->transition($demande, $cible, $request->user(), $reason, $request->ip());

            RequestDecision::create([
                'request_id' => $demande->id,
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role->value,
                'decision' => $decision->value,
                'reason' => $reason,
                'from_status' => $depuis->value,
                'to_status' => $cible->value,
            ]);
        });
    }
}
