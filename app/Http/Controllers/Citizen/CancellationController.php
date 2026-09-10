<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Services\PaymentService;
use App\Services\RequestTransitionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Cancel Request » — le demandeur retire sa demande.
 *
 * Une annulation n'est PAS une suppression : la demande reste en base, a
 * l'etat `cancelled`, avec sa trace. Effacer serait perdre le fait qu'une
 * demande a existe, ce qu'un audit anti-fraude doit pouvoir reconstituer.
 */
class CancellationController extends Controller
{
    public function __construct(
        private readonly RequestTransitionService $transitions,
        private readonly PaymentService $payments,
    ) {}

    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('cancel', $reissuanceRequest);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->transitions->transition(
                $reissuanceRequest,
                RequestStatus::Cancelled,
                $request->user(),
                $validated['reason'] ?? null,
                $request->ip(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        $message = "Votre demande {$reissuanceRequest->reference} a été annulée.";

        /*
         * Frais deja regles : on le DIT, on ne decide pas.
         *
         * La politique de remboursement est la question 4 d'INTEGRATIONS 5,
         * toujours ouverte (D-040). Rembourser automatiquement serait
         * inventer une regle ; se taire serait laisser croire que la somme est
         * perdue. On informe, et un agent tranchera.
         */
        if ($this->payments->isPaid($reissuanceRequest)) {
            $message .= ' Des frais ont été réglés pour cette demande : '
                ."rapprochez-vous de votre centre d'état civil, la suite dépend "
                .'des règles de remboursement en vigueur.';
        }

        return redirect()
            ->route('citizen.requests.index')
            ->with('status', $message);
    }
}
