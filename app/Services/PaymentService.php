<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Enums\PaymentOperator;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Support\Money;
use App\Support\PaymentIntent;
use App\Support\PaymentOutcome;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Seul point d'ecriture de payments.status.
 *
 * Meme dispositif que RequestTransitionService : l'audit est ecrit AVANT la
 * mise a jour, dans la meme transaction, et le declencheur MySQL exige de
 * le trouver. Une transition sans trace est impossible.
 */
final class PaymentService
{
    /**
     * Transitions autorisees. Doit rester identique au declencheur
     * phoenix_guard_payment_status ; un test verifie qu'ils ne divergent pas.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'pending' => ['authorised', 'settled', 'failed', 'expired'],
        'authorised' => ['settled', 'failed', 'expired'],
        'settled' => ['refunded'],
    ];

    public function __construct(private readonly PaymentProvider $provider) {}

    /**
     * Ouvre un encaissement pour une demande, ou rend celui qui est en cours.
     *
     * Idempotent par nature : une demande n'a qu'un encaissement vivant a la
     * fois. Un double clic, un rechargement de page ou un rejeu de formulaire
     * ne cree pas un second ordre.
     */
    public function initiate(
        ReissuanceRequest $request,
        User $actor,
        ?string $payerReference = null,
        ?PaymentOperator $operator = null,
    ): Payment {
        $existant = $this->livePayment($request);

        if ($existant !== null) {
            return $existant;
        }

        // Le tarif est lu ici, et sa lecture echoue si aucun n'est configure :
        // la plateforme refuse de servir plutot que de facturer un chiffre
        // invente (D-039).
        $montant = Money::fromConfig();

        $paiement = DB::transaction(function () use ($request, $actor, $montant, $payerReference, $operator): Payment {
            $paiement = Payment::create([
                'request_id' => $request->id,
                'initiated_by' => $actor->id,
                'amount_minor' => $montant->minorAmount,
                'currency' => $montant->currency,
                'minor_unit' => $montant->minorUnit,
                // Choix d'adaptateur, remplace par le nom que l'adaptateur
                // se donne des sa premiere reponse. Sans ce remplacement, la
                // colonne porterait « fake » la ou le code compare a
                // « fake-mobile-money » : le recu ne porterait jamais sa
                // mention de demonstration. Meme piege qu'en D-021.
                'provider' => (string) config('phoenix.providers.payment'),
                'idempotency_key' => (string) Str::uuid(),
                'payer_reference' => $payerReference,
                'operator' => $operator?->value,
            ]);

            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_role' => $actor->role->value,
                'action' => 'payment.initiated',
                'auditable_type' => 'payment',
                'auditable_id' => $paiement->id,
                'to_status' => PaymentStatus::Pending->value,
                // Le montant figure au journal : c'est une operation
                // financiere, elle doit etre reconstituable. Ce n'est pas une
                // donnee personnelle.
                'reason' => $montant->format(),
                'ip_address' => request()->ip(),
            ]);

            return $paiement;
        });

        $reponse = $this->provider->initiate(new PaymentIntent(
            $montant,
            $paiement->idempotency_key,
            $request->reference,
            $payerReference,
            $operator,
        ));

        return $this->apply($paiement, $reponse, $actor);
    }

    /**
     * Applique la reponse d'un operateur.
     *
     * Le coeur du rapprochement, et le point ou se joue l'idempotence : un
     * rappel rejoue qui annonce l'etat DEJA enregistre ne fait rien, sans
     * erreur. Les operateurs de paiement mobile rejouent, c'est normal.
     */
    public function apply(Payment $payment, PaymentOutcome $outcome, ?User $actor = null): Payment
    {
        $depuis = $payment->status;
        $vers = $outcome->status;

        if ($depuis === $vers) {
            // Rejeu : on enregistre ce que l'operateur a renvoye, sans
            // transition ni seconde ligne d'audit.
            $payment->forceFill([
                'provider' => $outcome->provider,
                'provider_reference' => $outcome->providerReference ?? $payment->provider_reference,
                'provider_payload' => $outcome->payload,
            ])->save();

            return $payment->refresh();
        }

        $this->assertAllowed($depuis, $vers);

        return DB::transaction(function () use ($payment, $outcome, $actor, $depuis, $vers): Payment {
            AuditLog::create([
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->role->value,
                'action' => "payment.{$depuis->value}_to_{$vers->value}",
                'auditable_type' => 'payment',
                'auditable_id' => $payment->id,
                'from_status' => $depuis->value,
                'to_status' => $vers->value,
                'reason' => $outcome->message,
                'ip_address' => request()->ip(),
            ]);

            $payment->forceFill(array_filter([
                'status' => $vers->value,
                'provider' => $outcome->provider,
                'provider_reference' => $outcome->providerReference ?? $payment->provider_reference,
                'provider_payload' => $outcome->payload,
                'authorised_at' => $vers === PaymentStatus::Authorised ? now() : $payment->authorised_at,
                'settled_at' => $vers === PaymentStatus::Settled ? now() : $payment->settled_at,
                'failed_at' => $vers === PaymentStatus::Failed ? now() : $payment->failed_at,
                'refunded_at' => $vers === PaymentStatus::Refunded ? now() : $payment->refunded_at,
                'failure_reason' => $vers === PaymentStatus::Failed
                    ? ($outcome->message ?? "Refus de l'opérateur, sans motif communiqué.")
                    : $payment->failure_reason,
            ], fn ($v): bool => $v !== null))->save();

            return $payment->refresh();
        });
    }

    /**
     * Interroge l'operateur et rapproche. Utile quand un rappel s'est perdu.
     *
     * Un rapprochement ne RECULE jamais et ne casse jamais. Si l'operateur
     * annonce un etat anterieur au notre — sa vue est en retard, ou un rappel
     * rejoue arrive apres coup — la transition est refusee par la machine a
     * etats, et on garde ce qu'on a. Laisser remonter l'exception ferait
     * repondre 500 a un rappel signe, donc reessayer le prestataire en
     * boucle, sur une route publique.
     */
    public function reconcile(Payment $payment): Payment
    {
        if ($payment->provider_reference === null || $payment->status->isTerminal()) {
            return $payment;
        }

        $reponse = $this->provider->status($payment->provider_reference);

        try {
            return $this->apply($payment, $reponse);
        } catch (DomainException $e) {
            Log::info('Rapprochement ignore : etat annonce non atteignable depuis l\'etat courant.', [
                'payment_id' => $payment->id,
                'from' => $payment->status->value,
                'to' => $reponse->status->value,
            ]);

            return $payment;
        }
    }

    /**
     * Rembourse.
     *
     * La POLITIQUE de remboursement — quand rembourse-t-on, et de combien ? —
     * n'est PAS tranchee : c'est la question 4 d'INTEGRATIONS 5. Cette methode
     * rend l'operation possible et tracee ; elle ne decide de rien, et n'est
     * appelee par aucun automatisme.
     */
    public function refund(Payment $payment, User $actor, string $reason): Payment
    {
        if (! $payment->isPaid()) {
            throw new DomainException(
                "Seul un encaissement acquis peut être remboursé (état actuel : {$payment->status->value})."
            );
        }

        if (mb_strlen(trim($reason)) < 10) {
            throw new DomainException('Un remboursement exige un motif explicite.');
        }

        return $this->apply(
            $payment,
            $this->provider->refund($payment, $payment->money(), $reason),
            $actor,
        );
    }

    /** L'encaissement vivant d'une demande, s'il y en a un. */
    public function livePayment(ReissuanceRequest $request): ?Payment
    {
        return Payment::query()
            ->where('request_id', $request->id)
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Authorised->value,
                PaymentStatus::Settled->value,
            ])
            ->latest('id')
            ->first();
    }

    /** La demande est-elle payee ? Seul `settled` compte. */
    public function isPaid(ReissuanceRequest $request): bool
    {
        return Payment::query()
            ->where('request_id', $request->id)
            ->where('status', PaymentStatus::Settled->value)
            ->exists();
    }

    public function assertAllowed(PaymentStatus $from, PaymentStatus $to): void
    {
        if ($from->isTerminal()) {
            throw new DomainException(
                "Transition interdite : {$from->value} est un état terminal."
            );
        }

        if (! in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new DomainException(
                "Transition interdite : {$from->value} → {$to->value}."
            );
        }
    }
}
