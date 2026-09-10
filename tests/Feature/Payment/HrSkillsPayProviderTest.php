<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\PaymentOperator;
use App\Enums\PaymentStatus;
use App\Integrations\Real\HrSkillsPayPaymentProvider;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentIntent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Adaptateur HR-Skills Pay, verifie contre un SERVEUR SIMULE.
 *
 * A DIRE SANS AMBIGUITE : aucun appel n'a ete fait contre le service reel.
 * Le contrat est releve dans la documentation publiee par le prestataire, et
 * ces tests verifient que l'adaptateur s'y conforme — pas que le prestataire
 * se conforme a sa propre documentation. La reprise contre le bac a sable,
 * avec de vrais identifiants, reste a faire (D-050).
 */
class HrSkillsPayProviderTest extends TestCase
{
    private const BASE = 'https://api.hrskills-pay.test';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'phoenix.payments.hrskills.base_url' => self::BASE,
            'phoenix.payments.hrskills.sandbox' => false,
            'phoenix.payments.hrskills.public_key' => 'hrsk_pk_test_CLE_A',
            'phoenix.payments.hrskills.secret_key' => 'hrsk_sk_test_CLE_B',
            'phoenix.payments.hrskills.timeout' => 5,
        ]);
    }

    private function provider(): HrSkillsPayPaymentProvider
    {
        return new HrSkillsPayPaymentProvider;
    }

    private function intent(?PaymentOperator $operateur = PaymentOperator::OrangeMoney, string $numero = '+237 6 55 50 03 93'): PaymentIntent
    {
        return new PaymentIntent(
            new Money(5000, 'XAF'),
            'cle-idempotence-0001',
            'PHX-TEST-0001',
            $numero,
            $operateur,
        );
    }

    /** @param array<string, mixed> $payin */
    private function fakeServer(array $payin = [], int $statutHttp = 200): void
    {
        Http::fake([
            self::BASE.'/v1/auth/transaction-token' => Http::response([
                'transaction_token' => 'eyJJETON', 'expires_in' => 2700,
            ]),
            self::BASE.'/api/v1/payin/mobile-money' => Http::response(
                ['data' => $payin + ['status' => 'PENDING', 'reference' => 'ref_abc123']],
                $statutHttp
            ),
            self::BASE.'/v1/payments/*' => Http::response(
                ['data' => ['status' => 'SUCCESS', 'reference' => 'ref_abc123', 'net_amount' => 4950]]
            ),
        ]);
    }

    /* ------------------------------------------------ la requete emise */

    #[Test]
    public function l_encaissement_est_emis_tel_que_la_documentation_le_decrit(): void
    {
        $this->fakeServer();

        $this->provider()->initiate($this->intent());

        Http::assertSent(function (Request $r): bool {
            if (! str_ends_with($r->url(), '/api/v1/payin/mobile-money')) {
                return false;
            }

            return $r->method() === 'POST'
                && $r->hasHeader('Authorization', 'Bearer hrsk_pk_test_CLE_A')
                && $r->hasHeader('X-Transaction-Token', 'eyJJETON')
                && $r->hasHeader('Idempotency-Key', 'cle-idempotence-0001')
                && $r['operator'] === 'ORANGE'
                && $r['country'] === 'CM'
                && $r['amount'] === 5000
                && $r['currency'] === 'XAF'
                && $r['phone_number'] === '237655500393';
        });
    }

    /** @return list<array{string, string}> */
    public static function numeros(): array
    {
        return [
            'avec indicatif et espaces' => ['+237 6 55 50 03 93', '237655500393'],
            'sans indicatif' => ['655500393', '237655500393'],
            'avec 00' => ['00237655500393', '237655500393'],
            'avec tirets' => ['237-655-500-393', '237655500393'],
        ];
    }

    #[Test]
    #[DataProvider('numeros')]
    public function le_numero_est_normalise_avant_envoi(string $saisi, string $attendu): void
    {
        $this->fakeServer();

        $this->provider()->initiate($this->intent(numero: $saisi));

        Http::assertSent(fn (Request $r): bool => ! str_contains($r->url(), 'auth')
            && $r['phone_number'] === $attendu);
    }

    #[Test]
    public function un_numero_incomplet_est_refuse_avant_tout_appel(): void
    {
        $this->fakeServer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/incomplet/');

        $this->provider()->initiate($this->intent(numero: '655'));
    }

    #[Test]
    public function l_operateur_de_MTN_est_transmis_sous_son_code(): void
    {
        $this->fakeServer();

        $this->provider()->initiate($this->intent(PaymentOperator::MtnMobileMoney));

        Http::assertSent(fn (Request $r): bool => ! str_contains($r->url(), 'auth')
            && $r['operator'] === 'MTN');
    }

    #[Test]
    public function sans_operateur_aucun_appel_n_est_emis(): void
    {
        $this->fakeServer();

        try {
            $this->provider()->initiate($this->intent(null));
            $this->fail('Un encaissement a été tenté sans opérateur.');
        } catch (RuntimeException) {
            // Attendu.
        }

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'payin'));
    }

    /* --------------------------------------------------- le jeton */

    #[Test]
    public function le_jeton_est_obtenu_une_fois_puis_reutilise(): void
    {
        $this->fakeServer();

        $this->provider()->initiate($this->intent());
        $this->provider()->initiate($this->intent());

        Http::assertSentCount(3); // 1 jeton + 2 encaissements
    }

    #[Test]
    public function une_authentification_refusee_echoue_sans_divulguer_la_cle(): void
    {
        Http::fake([
            self::BASE.'/v1/auth/transaction-token' => Http::response(['message' => 'invalid key'], 401),
        ]);

        try {
            $this->provider()->initiate($this->intent());
            $this->fail("L'authentification refusée n'a pas levé.");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringNotContainsString('hrsk_sk_test_CLE_B', $e->getMessage());
        }
    }

    /* ------------------------------------------------- les reponses */

    /** @return list<array{string, PaymentStatus}> */
    public static function statuts(): array
    {
        return [
            ['SUCCESS', PaymentStatus::Settled],
            ['COMPLETED', PaymentStatus::Settled],
            ['PENDING', PaymentStatus::Pending],
            ['FAILED', PaymentStatus::Failed],
            ['EXPIRED', PaymentStatus::Expired],
            // Un statut inconnu ne vaut JAMAIS « paye ».
            ['UNE_VALEUR_QUE_NOUS_NE_CONNAISSONS_PAS', PaymentStatus::Pending],
            ['', PaymentStatus::Pending],
        ];
    }

    #[Test]
    #[DataProvider('statuts')]
    public function les_statuts_sont_traduits_et_l_inconnu_n_est_jamais_un_paiement(string $rendu, PaymentStatus $attendu): void
    {
        $this->fakeServer(['status' => $rendu]);

        $this->assertSame($attendu, $this->provider()->initiate($this->intent())->status);
    }

    #[Test]
    public function un_service_injoignable_n_est_ni_un_paiement_ni_un_refus(): void
    {
        Http::fake([
            self::BASE.'/v1/auth/transaction-token' => Http::response([
                'transaction_token' => 'eyJJETON', 'expires_in' => 2700,
            ]),
            self::BASE.'/api/v1/payin/mobile-money' => fn () => throw new ConnectionException('timeout'),
        ]);

        $issue = $this->provider()->initiate($this->intent());

        $this->assertSame(PaymentStatus::Expired, $issue->status);
        $this->assertTrue($issue->isUnavailable());
    }

    #[Test]
    public function une_erreur_serveur_est_traitee_comme_une_indisponibilite(): void
    {
        $this->fakeServer([], 503);

        $issue = $this->provider()->initiate($this->intent());

        $this->assertSame(PaymentStatus::Expired, $issue->status);
        $this->assertTrue($issue->isUnavailable());
    }

    #[Test]
    public function un_refus_de_l_operateur_est_un_echec_pas_une_panne(): void
    {
        $this->fakeServer([], 402);

        $issue = $this->provider()->initiate($this->intent());

        $this->assertSame(PaymentStatus::Failed, $issue->status);
        $this->assertFalse($issue->isUnavailable());
    }

    /* ------------------------------------------- ce qui est conserve */

    /**
     * Liste blanche : la reponse du prestataire n'entre pas telle quelle en
     * base. Un numero de telephone rendu dans la reponse ne doit pas y etre
     * recopie sans decision (garde-fou n6).
     */
    #[Test]
    public function seuls_les_champs_retenus_sont_conserves(): void
    {
        $this->fakeServer([
            'status' => 'SUCCESS',
            'reference' => 'ref_abc123',
            'net_amount' => 4950,
            'fee' => 50,
            'phone_number' => '237655500393',
            'customer_name' => 'Personne DE TEST',
        ]);

        $charge = $this->provider()->initiate($this->intent())->payload;

        $this->assertSame(4950, $charge['net_amount']);
        $this->assertSame(50, $charge['fee']);
        $this->assertArrayNotHasKey('phone_number', $charge);
        $this->assertArrayNotHasKey('customer_name', $charge);
    }

    #[Test]
    public function le_rapprochement_interroge_la_transaction(): void
    {
        $this->fakeServer();

        $issue = $this->provider()->status('ref_abc123');

        $this->assertSame(PaymentStatus::Settled, $issue->status);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/v1/payments/ref_abc123'));
    }

    /* ---------------------------------------------------- les refus */

    #[Test]
    public function sans_identifiants_aucun_appel_n_est_emis(): void
    {
        config(['phoenix.payments.hrskills.secret_key' => '']);
        Http::fake();

        try {
            $this->provider()->initiate($this->intent());
            $this->fail('Un encaissement a été tenté sans identifiants.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SECRET_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    /** Une devise a subdivision serait convertie a tort : on refuse. */
    #[Test]
    public function une_devise_non_supportee_est_refusee(): void
    {
        $this->fakeServer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/XAF/');

        $this->provider()->initiate(new PaymentIntent(
            new Money(1234, 'EUR', 2), 'cle', 'PHX-TEST', '+237655500393', PaymentOperator::OrangeMoney,
        ));
    }

    /**
     * Le remboursement leve, et c'est deliberé.
     *
     * Aucun remboursement d'encaissement n'est documente. Un decaissement est
     * une operation distincte : la confondre avec un remboursement reviendrait
     * a virer des fonds sans lien comptable avec l'encaissement d'origine.
     */
    #[Test]
    public function le_remboursement_leve_plutot_que_de_bricoler_un_decaissement(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/décaissement/');

        $this->provider()->refund(new Payment, new Money(5000, 'XAF'), 'Motif suffisant.');

        Http::assertNothingSent();
    }

    #[Test]
    public function le_bac_a_sable_prefixe_les_chemins(): void
    {
        config(['phoenix.payments.hrskills.sandbox' => true]);

        Http::fake([
            self::BASE.'/sandbox/v1/auth/transaction-token' => Http::response([
                'transaction_token' => 'eyJJETON', 'expires_in' => 2700,
            ]),
            self::BASE.'/sandbox/api/v1/payin/mobile-money' => Http::response(
                ['data' => ['status' => 'PENDING', 'reference' => 'ref_sandbox']]
            ),
        ]);

        $this->provider()->initiate($this->intent());

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/sandbox/api/v1/payin/mobile-money'));
    }
}
