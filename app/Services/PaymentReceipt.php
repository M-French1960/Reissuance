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
    public static function demoNotice(): string
    {
        return __('documents.receipt_demo_notice');
    }

    public function __construct(private readonly HtmlToPdf $pdf) {}

    public function build(Payment $payment): string
    {
        $simule = $payment->provider === FakePaymentProvider::PROVIDER;
        $demande = $payment->request;
        $profil = $demande?->citizen?->profile;

        $elements = [
            __('documents.receipt_fields.request_reference') => (string) $demande?->reference,
            __('documents.receipt_fields.applicant') => (string) ($profil?->fullName() ?? '-'),
            __('documents.receipt_fields.amount_paid') => $payment->money()->format(),
            // 'a' is PHP's am/pm marker: it printed "10/09/2026 pm 13:55".
            __('documents.receipt_fields.payment_date') => $payment->settled_at?->format('d/m/Y H:i') ?? '-',
            __('documents.receipt_fields.payment_method') => $payment->operator?->label() ?? $payment->provider,
            __('documents.receipt_fields.transaction_reference') => (string) ($payment->provider_reference ?? '-'),
        ];

        $base = trim((string) config('phoenix.payments.legal_basis', ''));

        if ($base !== '') {
            $elements[__('documents.receipt_fields.legal_basis')] = $base;
        }

        return $this->pdf->render('documents.receipt', [
            'simule' => $simule,
            'mentionDemo' => self::demoNotice(),
            'elements' => $elements,
        ]);
    }
}
