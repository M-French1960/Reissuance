<?php

declare(strict_types=1);

namespace App\Integrations\Real;

use App\Contracts\PaymentProvider;
use App\Enums\PaymentOperator;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentIntent;
use App\Support\PaymentOutcome;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HR-Skills Pay — agregateur mobile money (Orange Money, MTN MoMo), Cameroun.
 *
 * SOURCE DU CONTRAT : la documentation publiee par le prestataire sur
 * hrskills-pay.com. **Aucun appel n'a ete fait contre le service reel** : sans
 * identifiants, cet adaptateur n'est verifie que contre un serveur simule. Il
 * est a reprendre contre le bac a sable avant toute mise en service (D-050).
 *
 * Deroulement, tel que documente :
 *
 *   1. POST {base}/v1/auth/transaction-token
 *      Authorization: Bearer <cle A>, corps {"api_secret": "<cle B>"}
 *      -> { transaction_token, expires_in }   (45 minutes)
 *
 *   2. POST {base}/api/v1/payin/mobile-money
 *      Authorization: Bearer <cle A>
 *      X-Transaction-Token: <jeton>
 *      Idempotency-Key: <uuid>
 *      corps { operator, country, phone_number, amount, currency }
 *      -> { data: { status, reference, net_amount } }
 *
 *   3. GET {base}/v1/payments/{reference}   pour rapprocher.
 *
 * Le bac a sable prefixe les chemins par /sandbox.
 */
final class HrSkillsPayPaymentProvider implements PaymentProvider
{
    public const PROVIDER = 'hrskills-pay';

    /** Le jeton vaut 45 min ; on le garde moins longtemps pour ne pas courir apres. */
    private const TOKEN_TTL_SECONDS = 2100;

    public function initiate(PaymentIntent $intent): PaymentOutcome
    {
        $this->assertConfigured();

        $montant = $this->assertSupported($intent->amount);

        try {
            $reponse = $this->client()
                ->withHeaders([
                    'X-Transaction-Token' => $this->transactionToken(),
                    // La cle d'idempotence de PHOENIX est reprise telle quelle :
                    // un rejeu de notre cote et un rejeu du leur designent alors
                    // la meme operation.
                    'Idempotency-Key' => $intent->idempotencyKey,
                ])
                ->post($this->url('/api/v1/payin/mobile-money'), [
                    'operator' => $this->operatorCode($intent->operator),
                    'country' => 'CM',
                    'phone_number' => $this->msisdn((string) $intent->payerReference),
                    'amount' => $montant->minorAmount,
                    'currency' => $montant->currency,
                ]);
        } catch (ConnectionException $e) {
            return $this->unreachable($e);
        }

        if ($reponse->failed()) {
            return $this->refused($reponse->json(), $reponse->status());
        }

        $donnees = (array) ($reponse->json('data') ?? []);

        return new PaymentOutcome(
            $this->mapStatus((string) ($donnees['status'] ?? '')),
            self::PROVIDER,
            $this->stringOrNull($donnees['reference'] ?? null),
            $this->message($donnees),
            $this->safePayload($donnees),
            $montant,
        );
    }

    public function status(string $providerReference): PaymentOutcome
    {
        $this->assertConfigured();

        try {
            $reponse = $this->client()
                ->withHeaders(['X-Transaction-Token' => $this->transactionToken()])
                ->get($this->url('/v1/payments/'.rawurlencode($providerReference)));
        } catch (ConnectionException $e) {
            return $this->unreachable($e);
        }

        if ($reponse->failed()) {
            return $this->refused($reponse->json(), $reponse->status(), $providerReference);
        }

        $donnees = (array) ($reponse->json('data') ?? []);

        return new PaymentOutcome(
            $this->mapStatus((string) ($donnees['status'] ?? '')),
            self::PROVIDER,
            $this->stringOrNull($donnees['reference'] ?? null) ?? $providerReference,
            $this->message($donnees),
            $this->safePayload($donnees),
        );
    }

    /**
     * Remboursement.
     *
     * La documentation relevee ne decrit AUCUN remboursement d'un encaissement
     * mobile money. Il existe un decaissement (`/api/v1/payout/mobile-money`),
     * mais c'est une operation distincte : elle envoie de l'argent, elle
     * n'annule pas une transaction. Les confondre reviendrait a virer des
     * fonds en croyant rembourser, sans lien comptable avec l'encaissement.
     *
     * On leve donc, plutot que de bricoler.
     */
    public function refund(Payment $payment, Money $amount, string $reason): PaymentOutcome
    {
        throw new RuntimeException(
            "HR-Skills Pay : aucun remboursement d'encaissement mobile money n'est documenté. "
            .'Un décaissement (payout) est une opération distincte, sans lien comptable avec '
            ."l'encaissement d'origine. La politique de remboursement reste par ailleurs à "
            .'définir (question 4 de docs/INTEGRATIONS.md §5).'
        );
    }

    /* ------------------------------------------------------------------ */

    private function assertConfigured(): void
    {
        foreach (['public_key', 'secret_key'] as $cle) {
            if (trim((string) config("phoenix.payments.hrskills.{$cle}")) === '') {
                throw new RuntimeException(
                    "HR-Skills Pay n'est pas configuré : PHOENIX_HRSKILLS_".strtoupper($cle).' est vide. '
                    .'Sans identifiants, aucun encaissement ne peut être ouvert.'
                );
            }
        }
    }

    /**
     * L'API prend un montant ENTIER en XAF.
     *
     * On refuse tout le reste plutot que de convertir : une conversion
     * silencieuse d'une devise a subdivision produirait un montant faux.
     */
    private function assertSupported(Money $montant): Money
    {
        if ($montant->currency !== 'XAF' || $montant->minorUnit !== 0) {
            throw new RuntimeException(
                "HR-Skills Pay n'encaisse qu'en XAF sans subdivision ; "
                ."reçu {$montant->currency} avec {$montant->minorUnit} décimale(s)."
            );
        }

        return $montant;
    }

    private function operatorCode(?PaymentOperator $operateur): string
    {
        return match ($operateur) {
            PaymentOperator::OrangeMoney => 'ORANGE',
            PaymentOperator::MtnMobileMoney => 'MTN',
            null => throw new RuntimeException(
                "HR-Skills Pay exige l'opérateur choisi par le demandeur."
            ),
        };
    }

    /**
     * Met le numero au format attendu : chiffres seuls, indicatif compris.
     *
     * HYPOTHESE, signalee : un numero mobile camerounais compte 9 chiffres,
     * et l'exemple de la documentation est « 237655500393 » — soit 237 suivi
     * de 9 chiffres. On prefixe donc 237 a un numero de 9 chiffres.
     *
     * Ce qu'on ne fait PAS : deduire l'operateur du prefixe. C'est le
     * demandeur qui choisit, et l'operateur qui reconnait ses numeros.
     */
    private function msisdn(string $saisi): string
    {
        $chiffres = preg_replace('/\D+/', '', $saisi) ?? '';

        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);
        }

        if (strlen($chiffres) === 9) {
            $chiffres = '237'.$chiffres;
        }

        if (strlen($chiffres) < 11) {
            throw new RuntimeException(
                'Numéro de règlement incomplet. Indiquez-le avec son indicatif, '
                .'par exemple +237 6 XX XX XX XX.'
            );
        }

        return $chiffres;
    }

    /** Le jeton de transaction, mis en cache le temps de sa validite. */
    private function transactionToken(): string
    {
        return Cache::remember(
            'phoenix:hrskills:transaction-token',
            self::TOKEN_TTL_SECONDS,
            function (): string {
                $reponse = Http::acceptJson()
                    ->timeout((int) config('phoenix.payments.hrskills.timeout'))
                    ->withToken((string) config('phoenix.payments.hrskills.public_key'))
                    ->post($this->url('/v1/auth/transaction-token'), [
                        'api_secret' => (string) config('phoenix.payments.hrskills.secret_key'),
                    ]);

                $jeton = $this->stringOrNull($reponse->json('transaction_token'));

                if (! $reponse->successful() || $jeton === null) {
                    // Le corps peut porter la cle secrete en echo : on ne
                    // journalise que le code HTTP.
                    throw new RuntimeException(
                        "HR-Skills Pay a refusé l'authentification (HTTP {$reponse->status()}). "
                        .'Vérifiez les clés A et B.'
                    );
                }

                return $jeton;
            }
        );
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('phoenix.payments.hrskills.timeout'))
            ->withToken((string) config('phoenix.payments.hrskills.public_key'));
    }

    private function url(string $chemin): string
    {
        $base = rtrim((string) config('phoenix.payments.hrskills.base_url'), '/');
        $prefixe = config('phoenix.payments.hrskills.sandbox') ? '/sandbox' : '';

        return $base.$prefixe.$chemin;
    }

    /**
     * Correspondance des statuts.
     *
     * Un statut INCONNU est traite comme « en attente », jamais comme
     * « paye » : se tromper dans ce sens ferait delivrer un acte contre un
     * encaissement qui n'a pas eu lieu.
     */
    private function mapStatus(string $statut): PaymentStatus
    {
        return match (strtoupper(trim($statut))) {
            'SUCCESS', 'COMPLETED', 'SUCCESSFUL' => PaymentStatus::Settled,
            'FAILED', 'REJECTED', 'CANCELLED', 'CANCELED' => PaymentStatus::Failed,
            'EXPIRED', 'TIMEOUT' => PaymentStatus::Expired,
            default => PaymentStatus::Pending,
        };
    }

    private function unreachable(ConnectionException $e): PaymentOutcome
    {
        Log::warning('HR-Skills Pay injoignable.', ['exception' => $e::class]);

        return new PaymentOutcome(
            PaymentStatus::Expired,
            self::PROVIDER,
            null,
            "L'opérateur de paiement est injoignable. Réessayez dans un moment.",
            ['unavailable' => true],
        );
    }

    /** @param  array<string, mixed>|null  $corps */
    private function refused(?array $corps, int $statut, ?string $reference = null): PaymentOutcome
    {
        return new PaymentOutcome(
            $statut >= 500 ? PaymentStatus::Expired : PaymentStatus::Failed,
            self::PROVIDER,
            $reference,
            $this->stringOrNull($corps['message'] ?? null)
                ?? "Le règlement a été refusé par l'opérateur (HTTP {$statut}).",
            ['http_status' => $statut, 'unavailable' => $statut >= 500],
        );
    }

    /** @param  array<string, mixed>  $donnees */
    private function message(array $donnees): ?string
    {
        return $this->stringOrNull($donnees['message'] ?? null);
    }

    /**
     * Ce qu'on conserve de la reponse.
     *
     * Liste BLANCHE, pas liste noire : recopier la reponse entiere ferait
     * entrer en base tout ce que le prestataire y met aujourd'hui ou y
     * ajoutera demain — numero de telephone compris.
     *
     * `net_amount` est conserve : le montant credite au beneficiaire diffère
     * du montant paye par le demandeur, frais deduits. Sans lui, aucun
     * rapprochement comptable n'est possible.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function safePayload(array $donnees): array
    {
        $garde = ['status', 'reference', 'net_amount', 'fee', 'amount', 'currency', 'operator'];

        return array_filter(
            array_intersect_key($donnees, array_flip($garde)),
            fn ($v): bool => $v !== null,
        );
    }

    private function stringOrNull(mixed $valeur): ?string
    {
        return is_string($valeur) && trim($valeur) !== '' ? $valeur : null;
    }
}
