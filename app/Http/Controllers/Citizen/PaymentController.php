<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Enums\PaymentOperator;
use App\Http\Controllers\Controller;
use App\Models\ReissuanceRequest;
use App\Services\PaymentGate;
use App\Services\PaymentReceipt;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reglement des frais par le demandeur.
 *
 * Un seul ecran sert les DEUX placements possibles (D-041) : avant l'envoi de
 * la demande, ou avant la signature du maire. Ce qui change est le moment ou
 * l'on y arrive, pas ce qu'on y fait.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGate $gate,
        private readonly PaymentReceipt $receipts,
    ) {}

    public function show(Request $request, ReissuanceRequest $reissuanceRequest): View|RedirectResponse
    {
        $this->authorize('view', $reissuanceRequest);

        if (! $this->gate->isEnabled()) {
            return redirect()
                ->route('citizen.requests.show', $reissuanceRequest)
                ->with('status', "Aucun frais n'est demandé pour cette démarche.");
        }

        return view('citizen.payment', [
            'demande' => $reissuanceRequest,
            'paiement' => $this->payments->livePayment($reissuanceRequest),
            'montant' => $this->gate->amount(),
            'baseLegale' => $this->gate->legalBasis(),
            'avantEnvoi' => $this->gate->requiredBeforeSubmission(),
            'operateurs' => PaymentOperator::all(),
        ]);
    }

    public function store(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('view', $reissuanceRequest);

        abort_unless($this->gate->isEnabled(), 404);

        $validated = $request->validate([
            'operator' => ['required', Rule::enum(PaymentOperator::class)],
            'payer_reference' => ['required', 'string', 'max:64'],
        ], [
            'operator.required' => 'Choisissez comment vous souhaitez régler.',
            'payer_reference.required' => 'Indiquez le numéro depuis lequel vous réglez.',
        ]);

        try {
            $paiement = $this->payments->initiate(
                $reissuanceRequest,
                $request->user(),
                $validated['payer_reference'],
                PaymentOperator::from($validated['operator']),
            );
        } catch (RuntimeException $e) {
            // Un fournisseur absent ou injoignable : on le DIT, on ne simule
            // pas un succes. Un citoyen qui croit avoir payé sans avoir payé
            // est la pire défaillance possible ici.
            return back()->withErrors(['payer_reference' => $e->getMessage()]);
        }

        return redirect()
            ->route('citizen.requests.payment', $reissuanceRequest)
            ->with('status', $paiement->status->label().'.');
    }

    /**
     * Rapprochement a la demande du citoyen.
     *
     * Un rappel d'operateur peut se perdre. Sans ce bouton, un paiement
     * effectivement acquitte resterait « en attente » indefiniment, et le
     * demandeur n'aurait aucun recours que d'appeler un guichet.
     */
    public function reconcile(Request $request, ReissuanceRequest $reissuanceRequest): RedirectResponse
    {
        $this->authorize('view', $reissuanceRequest);

        $paiement = $this->payments->livePayment($reissuanceRequest);

        if ($paiement === null) {
            return back()->withErrors(['payer_reference' => "Aucun règlement n'est en cours."]);
        }

        try {
            $paiement = $this->payments->reconcile($paiement);
        } catch (RuntimeException $e) {
            return back()->withErrors(['payer_reference' => $e->getMessage()]);
        }

        return back()->with('status', 'État du règlement : '.$paiement->status->label().'.');
    }

    /** Le recu, uniquement pour un encaissement acquis. */
    public function receipt(Request $request, ReissuanceRequest $reissuanceRequest): StreamedResponse
    {
        $this->authorize('view', $reissuanceRequest);

        $paiement = $this->payments->livePayment($reissuanceRequest);

        // 404 et non 403 : un identifiant sequentiel ne doit pas permettre de
        // confirmer l'existence d'un reglement.
        abort_unless($paiement !== null && $paiement->isPaid(), 404);

        $pdf = $this->receipts->build($paiement->load('request.citizen.profile'));

        return response()->streamDownload(
            fn () => print ($pdf),
            "recu-{$reissuanceRequest->reference}.pdf",
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, private, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
