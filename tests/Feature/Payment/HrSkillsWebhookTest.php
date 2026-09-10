<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Contracts\PaymentProvider;
use App\Enums\PaymentOperator;
use App\Enums\PaymentStatus;
use App\Integrations\Real\HrSkillsPayPaymentProvider;
use App\Models\CivilStatusCenter;
use App\Models\Payment;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rappels de l'operateur de paiement.
 *
 * C'est la route la plus exposee du systeme : publique, appelee par un tiers,
 * et qui touche a l'argent. Un rappel accepte a tort vaut un acte d'etat civil
 * delivre sans paiement.
 */
class HrSkillsWebhookTest extends TestCase
{
    private const SECRET = 'secret-de-rappel-pour-les-tests';

    private const BASE = 'https://api.hrskills-pay.test';

    private Payment $paiement;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'phoenix.payments.hrskills.webhook_secret' => self::SECRET,
            'phoenix.payments.hrskills.base_url' => self::BASE,
            'phoenix.payments.hrskills.sandbox' => false,
            'phoenix.payments.hrskills.public_key' => 'hrsk_pk_test_A',
            'phoenix.payments.hrskills.secret_key' => 'hrsk_sk_test_B',
            'phoenix.payments.amount_minor' => '1000',
            'phoenix.payments.currency' => 'XAF',
            'phoenix.payments.minor_unit' => 0,
        ]);

        $centre = CivilStatusCenter::factory()->create();
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-370000001', 'completed_at' => now(),
        ]);

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);

        $this->paiement = app(PaymentService::class)->initiate(
            $demande, $citoyen, '+237655500393', PaymentOperator::OrangeMoney
        );

        $this->paiement->forceFill([
            'provider' => HrSkillsPayPaymentProvider::PROVIDER,
            'provider_reference' => 'ref_abc123',
        ])->save();
    }

    /** @param array<string, mixed> $corps */
    private function envoyer(array $corps, ?string $signature = null, string $evenement = 'payment.succeeded'): TestResponse
    {
        $brut = json_encode($corps, JSON_UNESCAPED_UNICODE);

        return $this->call(
            'POST',
            route('webhooks.hrskills'),
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE' => $signature ?? 'sha256='.hash_hmac('sha256', $brut, self::SECRET),
                'HTTP_X_WEBHOOK_EVENT' => $evenement,
            ],
            $brut,
        );
    }

    private function fakeProvider(string $statut = 'SUCCESS'): void
    {
        Http::fake([
            self::BASE.'/v1/auth/transaction-token' => Http::response([
                'transaction_token' => 'eyJJETON', 'expires_in' => 2700,
            ]),
            self::BASE.'/v1/payments/*' => Http::response(
                ['data' => ['status' => $statut, 'reference' => 'ref_abc123', 'net_amount' => 990]]
            ),
        ]);

        $this->app->bind(PaymentProvider::class, fn () => new HrSkillsPayPaymentProvider);
    }

    /* -------------------------------------------------- la signature */

    #[Test]
    public function un_rappel_sans_signature_est_refuse(): void
    {
        Http::fake();

        $this->envoyer(['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_abc123']], signature: '')
            ->assertForbidden();

        $this->assertFalse($this->paiement->refresh()->isPaid());
        $this->assertSame(PaymentStatus::Authorised, $this->paiement->refresh()->status);
    }

    #[Test]
    public function un_rappel_mal_signe_est_refuse(): void
    {
        Http::fake();

        $this->envoyer(
            ['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_abc123']],
            signature: 'sha256='.hash_hmac('sha256', 'un autre corps', self::SECRET),
        )->assertForbidden();

        $this->assertFalse($this->paiement->refresh()->isPaid());
        $this->assertSame(PaymentStatus::Authorised, $this->paiement->refresh()->status);
    }

    /**
     * LE test qui compte : sans secret configure, aucun rappel n'est accepte.
     *
     * Le mode ouvert serait ici une porte d'entree vers des actes non payes.
     */
    #[Test]
    public function sans_secret_configure_tout_rappel_est_refuse(): void
    {
        config(['phoenix.payments.hrskills.webhook_secret' => '']);
        Http::fake();

        $this->envoyer(['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_abc123']])
            ->assertForbidden();

        $this->assertFalse($this->paiement->refresh()->isPaid());
        $this->assertSame(PaymentStatus::Authorised, $this->paiement->refresh()->status);
    }

    /* --------------------------------------------- la charge n'est pas crue */

    /**
     * Un rappel signe annonce « paye » — on RE-INTERROGE quand meme.
     *
     * Une signature prouve l'origine, pas la fraicheur ni l'exactitude du
     * contenu. La seule source de verite est l'etat rendu par le prestataire.
     */
    #[Test]
    public function un_rappel_valide_declenche_un_rapprochement_et_non_une_recopie(): void
    {
        $this->fakeProvider('SUCCESS');

        $this->envoyer(['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_abc123']])
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertSame(PaymentStatus::Settled, $this->paiement->refresh()->status);

        Http::assertSent(fn ($r): bool => str_ends_with($r->url(), '/v1/payments/ref_abc123'));
    }

    /**
     * Le cas d'attaque : un rappel signe qui MENT.
     *
     * Le prestataire dit « en attente » ; la charge dit « paye ». C'est l'etat
     * du prestataire qui l'emporte, et la demande ne devient pas payee.
     */
    #[Test]
    public function une_charge_qui_annonce_un_paiement_que_le_prestataire_dement_ne_paie_rien(): void
    {
        $this->fakeProvider('PENDING');

        $this->envoyer([
            'event' => 'payment.succeeded',
            'data' => ['reference' => 'ref_abc123', 'status' => 'SUCCESS', 'amount' => 999999],
        ])->assertOk();

        $this->assertFalse($this->paiement->refresh()->isPaid());
        $this->assertSame(PaymentStatus::Authorised, $this->paiement->refresh()->status);
        $this->assertFalse($this->paiement->refresh()->isPaid());
    }

    /* ------------------------------------------------------ robustesse */

    #[Test]
    public function un_rappel_pour_une_transaction_inconnue_est_ignore_sans_erreur(): void
    {
        $this->fakeProvider();

        $this->envoyer(['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_inconnue']])
            ->assertOk();

        $this->assertFalse($this->paiement->refresh()->isPaid());
        $this->assertSame(PaymentStatus::Authorised, $this->paiement->refresh()->status);
    }

    #[Test]
    public function un_rappel_sans_reference_est_ignore_sans_erreur(): void
    {
        $this->fakeProvider();

        $this->envoyer(['event' => 'payment.succeeded', 'data' => []])->assertOk();
    }

    #[Test]
    public function un_rappel_valide_est_journalise(): void
    {
        $this->fakeProvider();

        $this->envoyer(['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_abc123']]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.webhook_received',
            'auditable_type' => 'payment',
            'auditable_id' => $this->paiement->id,
            'reason' => 'payment.succeeded',
        ]);
    }

    /** Le rejeu d'un meme rappel ne change rien : l'idempotence tient. */
    #[Test]
    public function un_rappel_rejoue_ne_fait_rien_de_plus(): void
    {
        $this->fakeProvider('SUCCESS');

        $corps = ['event' => 'payment.succeeded', 'data' => ['reference' => 'ref_abc123']];

        foreach ([1, 2, 3] as $ignore) {
            $this->envoyer($corps)->assertOk();
        }

        $this->assertSame(PaymentStatus::Settled, $this->paiement->refresh()->status);
        $this->assertSame(1, \DB::table('audit_logs')
            ->where('auditable_type', 'payment')
            ->where('auditable_id', $this->paiement->id)
            ->where('action', 'payment.authorised_to_settled')
            ->count());
    }
}
