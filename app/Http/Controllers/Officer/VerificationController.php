<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class VerificationController extends Controller
{
    public function __construct(
        private readonly VerificationWorkflow $workflow,
        private readonly RequestTransitionService $transitions,
    ) {}

    /** Prise en charge : transition T3. */
    public function claim(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('claim', $reissuanceRequest);

        DB::transaction(function () use ($request, $reissuanceRequest): void {
            $this->transitions->transition(
                $reissuanceRequest,
                RequestStatus::UnderReview,
                $request->user(),
                null,
                $request->ip(),
            );

            $reissuanceRequest->forceFill([
                'assigned_officer_id' => $request->user()->id,
            ])->save();
        });

        return redirect()->route('officer.verification.step', [
            'reissuanceRequest' => $reissuanceRequest, 'step' => 1,
        ]);
    }

    public function show(Request $request, ReissuanceRequest $reissuanceRequest, int $step): View|RedirectResponse
    {
        $this->authorize('view', $reissuanceRequest);

        if ($step < 1 || $step > 5) {
            abort(404);
        }

        return view("officer.verification.step-{$step}", [
            'demande' => $reissuanceRequest->load('citizen.profile', 'attachments', 'center', 'commune'),
            'step' => $step,
            'steps' => VerificationWorkflow::STEPS,
            'etapes' => $this->workflow->steps($reissuanceRequest),
            'manquantes' => $this->workflow->missingSteps($reissuanceRequest),
            'complet' => $this->workflow->isComplete($reissuanceRequest),
            'peutDecider' => $request->user()->can('decide', $reissuanceRequest),
        ]);
    }

    /** Étapes 1 et 3 : constat de l'officier, sans appel externe. */
    public function acknowledge(Request $request, ReissuanceRequest $reissuanceRequest, int $step): RedirectResponse
    {
        $this->authorize('decide', $reissuanceRequest);

        abort_unless(in_array($step, [1, 3], true), 404);

        $validated = $request->validate([
            'result' => ['required', Rule::in([
                VerificationResult::Match->value,
                VerificationResult::NoMatch->value,
                VerificationResult::Inconclusive->value,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'result.required' => 'Indiquez le résultat de votre contrôle avant de continuer.',
        ]);

        $this->workflow->record(
            $reissuanceRequest, $step, $request->user(),
            VerificationResult::from($validated['result']),
            ['note' => $validated['note'] ?? null, 'source' => 'officer_observation'],
        );

        return redirect()->route('officer.verification.step', [
            'reissuanceRequest' => $reissuanceRequest, 'step' => $step + 1,
        ]);
    }

    /** Étape 2 : appel à la base de la police. */
    public function runIdentityCheck(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('decide', $reissuanceRequest);

        try {
            $reponse = $this->workflow->runIdentityCheck($reissuanceRequest, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['provider' => $e->getMessage()]);
        }

        return back()->with('status', $reponse->message ?? $reponse->outcome->label());
    }

    /** Étape 4 : recherche dans le registre. */
    public function runRegistrySearch(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('decide', $reissuanceRequest);

        try {
            $reponse = $this->workflow->runRegistrySearch($reissuanceRequest, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['provider' => $e->getMessage()]);
        }

        return back()->with('status', $reponse->message ?? $reponse->outcome->label());
    }
}
