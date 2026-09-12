<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\Fake\FakePaymentProvider;
use App\Models\Payment;
use App\Support\Pdf\HtmlToPdf;

/**
 * Recu de reglement.
 *
 * Meme regle qu'a l'acte (D-025) : tant que l'encaissement passe par
 * l'adaptateur factice, le recu porte en PREMIERE LIGNE la mention qui dit
 * qu'aucune somme n'a ete encaissee. Un recu de demonstration ne doit pouvoir
 * etre confondu avec une quittance par personne.
 *
 * Ce que le recu NE dit PAS : la base reglementaire du tarif, tant qu'elle
 * n'est pas configuree. On n'imprime pas un fondement juridique qu'on ne peut
 * pas citer (10 du brief).
 */
final class PaymentReceipt
{
    public const DEMO_NOTICE = 'RECU DE DEMONSTRATION - AUCUNE SOMME N\'A ETE ENCAISSEE';

    public function __construct(private readonly HtmlToPdf $pdf) {}

    public function build(Payment $payment): string
    {
        $simule = $payment->provider === FakePaymentProvider::PROVIDER;
        $demande = $payment->request;
        $profil = $demande?->citizen?->profile;

        $elements = [
            'Référence de la demande' => (string) $demande?->reference,
            'Demandeur' => (string) ($profil?->fullName() ?? '-'),
            'Montant réglé' => $payment->money()->format(),
            // 'a' est le marqueur am/pm de PHP : il imprimait « 10/09/2026 pm
            // 13:55 ».
            'Date du règlement' => $payment->settled_at?->format('d/m/Y H:i') ?? '-',
            'Moyen de paiement' => $payment->operator?->label() ?? $payment->provider,
            'Référence de transaction' => (string) ($payment->provider_reference ?? '-'),
        ];

        $base = trim((string) config('phoenix.payments.legal_basis', ''));

        if ($base !== '') {
            $elements['Base réglementaire'] = $base;
        }

        return $this->pdf->render('documents.receipt', [
            'simule' => $simule,
            'mentionDemo' => self::DEMO_NOTICE,
            'elements' => $elements,
        ]);
    }
}
