<?php

declare(strict_types=1);

namespace App\Integrations\Fake;

use App\Contracts\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentIntent;
use App\Support\PaymentOutcome;
use Illuminate\Support\Str;

/**
 * Adaptateur factice d'encaissement.
 *
 * Deterministe : la meme reference de payeur produit toujours le meme
 * resultat, pour que les jeux de demonstration et les tests couvrent les cas
 * degrades et pas seulement le cas heureux.
 *
 * Le declenchement se fait par PREFIXE de la reference du payeur, apres
 * normalisation — meme piege qu'au jalon 4, ou des declencheurs ecrits avec
 * des tirets ne correspondaient jamais a la valeur relue (D-021).
 *
 * CE FOURNISSEUR N'ENCAISSE RIEN. Il ne represente aucun operateur reel.
 */
final class FakePaymentProvider implements PaymentProvider
{
    public const PROVIDER = 'fake-mobile-money';

    /** @var array<string, PaymentStatus> */
    public const TRIGGERS = [
        'DEMOREFUS' => PaymentStatus::Failed,
        'DEMOSOLDE' => PaymentStatus::Failed,
        'DEMOATTENTE' => PaymentStatus::Pending,
        'DEMOEXPIRE' => PaymentStatus::Expired,
        'DEMOPANNE' => PaymentStatus::Expired,
    ];

    public function initiate(PaymentIntent $intent): PaymentOutcome
    {
        // L'operateur choisi ne change pas le comportement simule : un
        // agregateur expose la meme interface pour les deux. Il est conserve
        // dans la charge pour que le rapprochement comptable le retrouve.
        $reference = self::normalise((string) $intent->payerReference);

        foreach (self::TRIGGERS as $prefixe => $etat) {
            if ($reference !== '' && str_starts_with($reference, $prefixe)) {
                return $this->repondre($etat, $prefixe, $intent->amount);
            }
        }

        // Cas nominal : l'operateur prend l'ordre. Les fonds ne sont pas
        // encore acquis — c'est le point que « autorise » ne doit pas laisser
        // confondre avec « paye ».
        return new PaymentOutcome(
            PaymentStatus::Authorised,
            self::PROVIDER,
            'FAKE-'.Str::upper(Str::random(10)),
            "Ordre pris en compte par l'opérateur. Les fonds ne sont pas encore acquis.",
            [
                'simulated' => true,
                'amount_minor' => $intent->amount->minorAmount,
                'operator' => $intent->operator?->value,
            ],
            $intent->amount,
        );
    }

    public function status(string $providerReference): PaymentOutcome
    {
        // Un ordre pris en compte finit par etre acquis, dans le cas nominal.
        return new PaymentOutcome(
            PaymentStatus::Settled,
            self::PROVIDER,
            $providerReference,
            'Fonds acquis.',
            ['simulated' => true],
        );
    }

    public function refund(Payment $payment, Money $amount, string $reason): PaymentOutcome
    {
        return new PaymentOutcome(
            PaymentStatus::Refunded,
            self::PROVIDER,
            $payment->provider_reference,
            'Remboursement simulé : '.$amount->format(),
            ['simulated' => true, 'reason' => $reason],
            $amount,
        );
    }

    private function repondre(PaymentStatus $etat, string $prefixe, Money $montant): PaymentOutcome
    {
        [$message, $charge] = match ($prefixe) {
            'DEMOREFUS' => ['Paiement refusé par le payeur.', []],
            'DEMOSOLDE' => ['Solde insuffisant.', []],
            'DEMOATTENTE' => ['En attente de validation sur le téléphone du payeur.', []],
            'DEMOEXPIRE' => ["Le payeur n'a pas validé dans le délai imparti.", []],
            'DEMOPANNE' => ["L'opérateur est injoignable.", ['unavailable' => true]],
        };

        return new PaymentOutcome(
            $etat,
            self::PROVIDER,
            $etat === PaymentStatus::Pending ? 'FAKE-'.Str::upper(Str::random(10)) : null,
            $message,
            $charge + ['simulated' => true, 'trigger' => $prefixe],
            $montant,
        );
    }

    /** Meme normalisation que BlindIndex : sans elle, la ponctuation decide. */
    public static function normalise(string $valeur): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($valeur), 'UTF-8')) ?? '';
    }
}
