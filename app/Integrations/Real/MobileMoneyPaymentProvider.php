<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\PaymentProvider;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentIntent;
use App\Support\PaymentOutcome;
use RuntimeException;

/**
 * Squelette d'un encaissement reel.
 *
 * IL N'EXISTE PAS. Aucune des six questions d'INTEGRATIONS 5 n'a de reponse :
 * ni le tarif, ni son fondement, ni qui encaisse, ni quel agregateur, ni le
 * sort d'un paiement quand la demande est rejetee.
 *
 * Cette classe leve une exception explicite plutot que de rendre un succes
 * simule. Un encaissement qui « marche » sans encaisser est la pire des
 * defaillances possibles pour un service public : le citoyen croit avoir paye.
 */
final class MobileMoneyPaymentProvider implements PaymentProvider
{
    public function initiate(PaymentIntent $intent): PaymentOutcome
    {
        throw $this->indisponible();
    }

    public function status(string $providerReference): PaymentOutcome
    {
        throw $this->indisponible();
    }

    public function refund(Payment $payment, Money $amount, string $reason): PaymentOutcome
    {
        throw $this->indisponible();
    }

    private function indisponible(): RuntimeException
    {
        return new RuntimeException(
            "Aucun encaissement réel n'est branché. Six questions restent sans réponse : "
            ."tarif officiel et base réglementaire, bénéficiaire de l'encaissement, "
            ."moment du paiement, sort d'un paiement après rejet, opérateurs et agrégateur, "
            .'obligations de reçu. Voir docs/INTEGRATIONS.md §5.'
        );
    }
}
