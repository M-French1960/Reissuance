<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\Fake\FakePaymentProvider;
use App\Models\Payment;
use App\Support\Pdf\PdfDocument;

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

    public function build(Payment $payment): string
    {
        $pdf = new PdfDocument;
        $simule = $payment->provider === FakePaymentProvider::PROVIDER;

        if ($simule) {
            $pdf->text(self::DEMO_NOTICE, 12, true)->spacer(6)->rule()->spacer(10);
        }

        $demande = $payment->request;
        $profil = $demande?->citizen?->profile;

        $pdf->text('RECU DE REGLEMENT', 16, true)
            ->spacer(4)
            ->text("Reedition d'acte d'etat civil", 11)
            ->spacer(14)
            ->rule()
            ->spacer(10);

        $pdf->labelled('Reference de la demande', (string) $demande?->reference)
            ->labelled('Demandeur', (string) ($profil?->fullName() ?? '-'))
            ->labelled('Montant regle', $payment->money()->format())
            // 'a' est le marqueur am/pm de PHP : il imprimait « 10/09/2026 pm
            // 13:55 ». Le PDF est en WinAnsi, un « a » accentue y passe, mais
            // il n'apporte rien ici.
            ->labelled('Date du reglement', $payment->settled_at?->format('d/m/Y H:i') ?? '-')
            ->labelled('Moyen de paiement', $payment->provider)
            ->labelled('Reference de transaction', (string) ($payment->provider_reference ?? '-'));

        $base = trim((string) config('phoenix.payments.legal_basis', ''));

        if ($base !== '') {
            $pdf->labelled('Base reglementaire', $base);
        }

        $pdf->spacer(14)->rule()->spacer(10);

        if ($simule) {
            $pdf->text(self::DEMO_NOTICE, 11, true)
                ->spacer(6)
                ->paragraph(
                    'Ce document a ete produit par un adaptateur de demonstration. '
                    ."Aucun operateur de paiement n'a ete sollicite et aucune somme "
                    ."n'a change de main. Il ne vaut ni quittance, ni preuve de paiement."
                );
        }

        return $pdf->render();
    }
}
