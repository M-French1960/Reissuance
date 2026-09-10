<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentIntent;
use App\Support\PaymentOutcome;

/**
 * Encaissement des frais de reedition.
 *
 * ATTENTION — docs/INTEGRATIONS.md 5 : SIX questions sont sans reponse, dont
 * trois structurantes. Ce contrat est une HYPOTHESE de travail :
 *
 *   1. le tarif officiel et sa base reglementaire ;
 *   3. le paiement precede-t-il l'envoi de la demande, ou sa signature ?
 *   4. que devient un paiement quand la demande est rejetee ?
 *
 * La question 4 est celle qui pese sur ce contrat : `refund()` existe parce
 * qu'un systeme qui encaisse doit pouvoir rendre, PAS parce qu'une politique
 * de remboursement aurait ete arretee. Elle ne l'a pas ete.
 *
 * Le contrat suppose un paiement mobile en deux temps — un ordre initie, puis
 * une confirmation asynchrone — parce que c'est la forme des operateurs de
 * la region. Si l'encaissement se fait au guichet, ce contrat ne modelise pas
 * le bon flux.
 */
interface PaymentProvider
{
    /** Initie un ordre. La reponse ne vaut PAS encaissement. */
    public function initiate(PaymentIntent $intent): PaymentOutcome;

    /**
     * Etat courant d'un ordre, tel que l'operateur le connait.
     *
     * Necessaire meme avec des rappels : un rappel peut se perdre, et un
     * paiement qui reste « en attente » indefiniment doit pouvoir etre
     * rapproche.
     */
    public function status(string $providerReference): PaymentOutcome;

    /** Rembourse un encaissement acquis. */
    public function refund(Payment $payment, Money $amount, string $reason): PaymentOutcome;
}
