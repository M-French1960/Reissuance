<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use App\Services\ActDraftService;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Décision de l'officier : étape 5 (5.3 du brief).
 *
 * Trois défauts du prototype sont corrigés ici (docs/AUDIT_FRONTEND.md 5.5) :
 *
 *   - « Accepter » y était le choix PAR DÉFAUT du menu déroulant, sans motif.
 *     Un clic accidentel valait acceptation. Ici aucune valeur n'est
 *     présélectionnée.
 *   - La décision n'était jamais enregistrée : un alert() puis une redirection.
 *   - L'étape 5 était atteignable sans avoir lancé la moindre vérification.
 *     Ici, accepter exige que les 5 étapes aient un résultat.
 */
class DecisionController extends Controller
{
    /**
     * Motifs de rejet pré-remplis (8.2 du brief : optimiser la répétition).
     * L'officier reste libre de compléter.
     *
     * @var list<string>
     */
    /**
     * Prefilled reasons, in the agent's language.
     *
     * A method rather than a constant: a constant is resolved at compile time,
     * before any language has been chosen for the request, so it could only
     * ever hold one language.
     *
     * @return list<string>
     */
    public static function rejectionReasons(): array
    {
        return array_values((array) __('officer.rejection_reasons'));
    }

    public function __construct(
        private readonly VerificationWorkflow $workflow,
        private readonly RequestTransitionService $transitions,
        private readonly ActDraftService $drafts,
    ) {}

    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('decide', $reissuanceRequest);

        // Une acceptation malgré une vérification qui n'a pas abouti exige un
        // motif, au même titre qu'un rejet. L'officier garde son pouvoir de
        // décision ; il ne peut simplement plus l'exercer en silence (D-031).
        $reservations = $this->workflow->reservations($reissuanceRequest);

        $motifExige = $reservations !== []
            ? ['required', 'string', 'min:10', 'max:1000']
            : ['required_unless:decision,accepted', 'nullable', 'string', 'min:10', 'max:1000'];

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected', 'escalated'])],
            // Motif obligatoire dès que la décision n'est pas une acceptation.
            // La contrainte request_decisions_reason_required_check l'impose
            // aussi en base : ce contrôle rend seulement le message utilisable.
            'reason' => $motifExige,
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'decision.required' => __('flash.officer.decision_required'),
            'reason.required_unless' => __('flash.officer.reason_required'),
            'reason.required' => $reservations !== []
                ? __('flash.officer.reason_required_reservation')
                : __('flash.officer.reason_required'),
            'reason.min' => __('flash.officer.reason_min'),
        ]);

        $decision = DecisionType::from($validated['decision']);

        // La barrière anti-fraude : on n'accepte pas un dossier dont la
        // vérification n'a pas été menée. Le déclencheur MySQL ne peut
        // pas connaître cette règle — elle porte sur les étapes, pas sur le
        // couple de statuts — donc elle est vérifiée ici ET testée (R5).
        //
        // Ce sont les QUATRE vérifications qui sont exigées : la cinquième
        // étape est cette décision même, et l'exiger comme son propre
        // préalable rendait l'acceptation inatteignable (D-027).
        if ($decision === DecisionType::Accepted && ! $this->workflow->isComplete($reissuanceRequest)) {
            $manquantes = $this->workflow->missingSteps($reissuanceRequest);
            $libelles = array_map(
                fn (int $n): string => "{$n}. ".VerificationWorkflow::stepNames()[$n],
                $manquantes,
            );

            return back()->withErrors([
                'decision' => __('flash.officer.cannot_accept_incomplete', ['steps' => implode(', ', $libelles)]),
            ])->withInput();
        }

        $cible = match ($decision) {
            DecisionType::Accepted => RequestStatus::AwaitingSignature,
            DecisionType::Rejected => RequestStatus::Rejected,
            DecisionType::Escalated => RequestStatus::Escalated,
            default => abort(422),
        };

        $depuis = $reissuanceRequest->status;

        try {
            DB::transaction(function () use ($request, $reissuanceRequest, $decision, $cible, $depuis, $validated): void {
                $this->transitions->transition(
                    $reissuanceRequest, $cible, $request->user(),
                    $validated['reason'] ?? null, $request->ip(),
                );

                /*
                 * « Generate Certificate » : l'officier redige le projet
                 * d'acte (D-064, lecture 2 du diagramme).
                 *
                 * Sur LES DEUX chemins qui remettent le dossier au maire —
                 * acceptation (T4) et escalade (T6) — parce que tous deux
                 * peuvent mener a une signature. N'en couvrir qu'un rendrait
                 * la signature impossible apres une escalade.
                 *
                 * Rien de ce qui est produit ici n'a valeur d'acte : le projet
                 * porte son bandeau, vit dans sa propre table, et le citoyen
                 * n'y a aucun acces.
                 */
                if (in_array($cible, [RequestStatus::AwaitingSignature, RequestStatus::Escalated], true)) {
                    $this->drafts->draft($reissuanceRequest->refresh(), $request->user());
                }

                RequestDecision::create([
                    'request_id' => $reissuanceRequest->id,
                    'actor_id' => $request->user()->id,
                    'actor_role' => $request->user()->role->value,
                    'decision' => $decision->value,
                    'reason' => $validated['reason'] ?? null,
                    'internal_notes' => $validated['internal_notes'] ?? null,
                    'from_status' => $depuis->value,
                    'to_status' => $cible->value,
                ]);
            });
        } catch (DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()])->withInput();
        }

        // « Avancement dans la file sans retour au tableau de bord » (8.2) :
        // on enchaîne sur la demande suivante à prendre en charge, s'il y en a.
        $suivante = ReissuanceRequest::query()
            ->where('status', RequestStatus::Pending->value)
            ->whereNull('assigned_officer_id')
            ->orderBy('submitted_at')
            ->first();

        $message = __('flash.officer.decision_recorded', [
            'reference' => $reissuanceRequest->reference,
            'decision' => $decision->label(),
        ]);

        if ($suivante !== null) {
            return redirect()
                ->route('officer.queue')
                ->with('status', $message.' '.__('flash.officer.next_waiting'));
        }

        return redirect()->route('officer.queue')->with('status', $message);
    }
}
