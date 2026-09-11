<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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

    /**
     * Prise en charge d'un dossier : transition T3, ou simple reprise.
     *
     * Deux cas, et le second n'est pas une transition d'etat :
     *
     * - dossier « en attente » : c'est T3, pending -> under_review ;
     * - dossier deja « en cours d'examen » et libere par l'administrateur
     *   (D-057) : l'etat ne change pas, seule l'affectation. Appeler la
     *   transition ici leverait — under_review -> under_review n'existe pas
     *   dans la machine a etats, et il n'est pas question de l'y ajouter pour
     *   la commodite d'un controleur.
     *
     * Dans les deux cas l'operation est tracee : par la transition pour le
     * premier, par une ligne d'audit explicite pour le second.
     */
    public function claim(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('claim', $reissuanceRequest);

        DB::transaction(function () use ($request, $reissuanceRequest): void {
            if ($reissuanceRequest->status === RequestStatus::Pending) {
                $this->transitions->transition(
                    $reissuanceRequest,
                    RequestStatus::UnderReview,
                    $request->user(),
                    null,
                    $request->ip(),
                );
            } else {
                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'actor_role' => $request->user()->role->value,
                    'action' => 'request.assignment_resumed',
                    'auditable_type' => 'reissuance_request',
                    'auditable_id' => $reissuanceRequest->id,
                    'ip_address' => $request->ip(),
                ]);
            }

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
            'reservations' => $this->workflow->reservations($reissuanceRequest),
            'avisFacial' => $this->workflow->facialOpinion($reissuanceRequest),
            'messages' => $reissuanceRequest->messages()->with('author:id,name')->oldest()->get(),
            'peutDecider' => $request->user()->can('decide', $reissuanceRequest),
            // La vue doit pouvoir dire la VERITE sur l'etat du dossier, et
            // pas seulement « vous ne pouvez pas decider ». Trois cas
            // distincts, trois messages distincts (D-055).
            'peutPrendreEnCharge' => $request->user()->can('claim', $reissuanceRequest),
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

        // Etape 3 : la comparaison faciale est OBLIGATOIRE avant que
        // l'officier ne conclue. Elle ne decide pas a sa place — elle doit
        // simplement avoir eu lieu, et figurer au dossier.
        $avis = null;

        if ($step === 3) {
            $avis = $this->workflow->facialOpinion($reissuanceRequest);

            if ($avis === null) {
                return back()->withErrors([
                    'result' => 'Lancez la comparaison faciale avant de conclure sur les photographies.',
                ])->withInput();
            }
        }

        $this->workflow->record(
            $reissuanceRequest, $step, $request->user(),
            VerificationResult::from($validated['result']),
            array_filter([
                'note' => $validated['note'] ?? null,
                'source' => 'officer_observation',
                // L'avis de la machine est recopie dans l'etape : on saura
                // plus tard sur quoi l'officier s'est prononce, et s'il est
                // passe outre.
                'facial_outcome' => $avis['outcome'] ?? null,
                'facial_similarity' => $avis['payload']['similarity'] ?? null,
            ], fn ($v): bool => $v !== null),
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

    /**
     * Étape 3 : comparaison faciale.
     *
     * Séparée de l'enregistrement de l'étape, à dessein : l'officier lance la
     * comparaison, LIT le résultat, puis conclut lui-même. Fusionner les deux
     * ferait de l'avis de la machine la décision.
     */
    public function runFacialComparison(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('decide', $reissuanceRequest);

        try {
            $reponse = $this->workflow->runFacialComparison($reissuanceRequest, $request->user());
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
