<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mayor;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use App\Services\ActIssuanceService;
use App\Services\PaymentGate;
use App\Services\RequestTransitionService;
use App\Services\SignatureConfirmation;
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
        private readonly PaymentGate $gate,
        private readonly SignatureConfirmation $confirmation,
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
            // Le code de confirmation. Pas de `required` ici : le message
            // utile est celui de SignatureConfirmation, qui distingue un champ
            // vide d'un code faux et d'un compte bloqué.
            'confirmation_code' => ['nullable', 'string', 'max:64'],
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

        // Barriere de paiement, si le service l'a placee ici (D-041). Le maire
        // ne signe pas un acte dont les frais ne sont pas acquittes ; il n'a
        // pas non plus a savoir OU la barriere est posee.
        if (! $this->gate->allows($reissuanceRequest, PaymentGate::BEFORE_SIGNATURE)) {
            return back()->withErrors([
                'reason' => 'Cette demande ne peut pas être signée : les frais ne sont pas acquittés. '
                    .'Le demandeur doit régler avant la signature.',
            ])->withInput();
        }

        /*
         * LA CONFIRMATION D'IDENTITE, AVANT TOUT LE RESTE (D-069).
         *
         * Placee ici a dessein : avant la transition, avant la redaction du
         * PDF, avant le moindre appel au prestataire de signature. Un document
         * construit puis jete resterait un document construit sans decision du
         * maire, et un appel a un prestataire resterait un appel.
         */
        try {
            $methode = $this->confirmation->confirm(
                $request->user(),
                $validated['confirmation_code'] ?? null,
                $request->ip(),
            );
        } catch (DomainException $e) {
            return back()
                ->withErrors(['confirmation_code' => $e->getMessage()])
                // Le code n'est PAS renvoye a la vue : il vaut trente secondes
                // et n'a aucune raison de revenir dans le HTML.
                ->withInput($request->except('confirmation_code'));
        }

        $depuis = $reissuanceRequest->status;

        try {
            $signature = DB::transaction(function () use ($request, $reissuanceRequest, $validated, $depuis, $estEscaladee, $methode) {
                $signature = $this->issuance->issue(
                    $reissuanceRequest, $request->user(), $validated['reason'] ?? null, $methode
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
