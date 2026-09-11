<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\PaymentGate;
use App\Services\PaymentService;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * Les DEUX placements de la barriere de paiement (D-041).
 *
 * La question 3 d'INTEGRATIONS 5 est sans reponse : le paiement precede-t-il
 * l'envoi de la demande ou sa signature ? Les deux sont construits, et ces
 * tests verifient que chacun tient — et surtout que celui qui n'est pas
 * configure ne bloque rien.
 */
class PaymentGateTest extends TestCase
{
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $officier;

    private User $maire;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        config([
            'phoenix.payments.amount_minor' => '1000',
            'phoenix.payments.currency' => 'XAF',
            'phoenix.payments.minor_unit' => 0,
        ]);

        $this->centre = CivilStatusCenter::factory()->create(['is_active' => true]);
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-320000001', 'completed_at' => now(),
        ]);
    }

    /** Un brouillon complet, pieces comprises, pret a etre envoye. */
    private function brouillonPret(): ReissuanceRequest
    {
        $this->actingAs($this->citoyen->fresh());
        $this->post(route('citizen.requests.start'));

        $brouillon = ReissuanceRequest::withoutGlobalScopes()
            ->where('user_id', $this->citoyen->id)->latest('id')->firstOrFail();

        $this->post(route('citizen.requests.save', [$brouillon, 1]), [
            'reason' => 'lost', 'copies_requested' => 1,
        ]);
        $this->post(route('citizen.requests.save', [$brouillon, 2]), [
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->post(route('citizen.requests.save', [$brouillon, 3]), [
            'civil_status_center_id' => $this->centre->id,
        ]);

        foreach (['selfie', 'id_document'] as $type) {
            $this->post(route('citizen.requests.attachments.store', $brouillon), [
                'kind' => $type,
                'file' => UploadedFile::fake()->image("{$type}.jpg", 800, 600),
            ]);
        }

        return $brouillon->refresh();
    }

    private function jusquALaSignature(ReissuanceRequest $demande): ReissuanceRequest
    {
        $transitions = app(RequestTransitionService::class);
        $transitions->transition($demande, RequestStatus::UnderReview, $this->officier);
        $demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();

        $workflow = app(VerificationWorkflow::class);
        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record($demande->refresh(), $n, $this->officier, VerificationResult::Match);
        }

        $transitions->transition($demande->refresh(), RequestStatus::AwaitingSignature, $this->officier);

        // Le maire signe un PROJET établi par l'officier (D-064).
        $this->redigeLeProjet($demande, $this->officier);

        return $demande->refresh();
    }

    /* ---------------------------------------------------------------- none */

    #[Test]
    public function sans_barriere_le_parcours_n_est_pas_touche(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::NONE]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('citizen.requests.show', $brouillon));

        $this->assertSame(RequestStatus::Pending, $brouillon->refresh()->status);
    }

    #[Test]
    public function sans_barriere_l_ecran_de_paiement_renvoie_a_la_demande(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::NONE]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.payment', $brouillon))
            ->assertRedirect(route('citizen.requests.show', $brouillon));
    }

    /* ---------------------------------------------------- before_submission */

    #[Test]
    public function barriere_avant_envoi_une_demande_impayee_n_est_pas_transmise(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertRedirect(route('citizen.requests.payment', $brouillon));

        $this->assertSame(RequestStatus::Draft, $brouillon->refresh()->status);
        $this->assertNull($brouillon->submitted_at);
    }

    #[Test]
    public function barriere_avant_envoi_une_demande_payee_passe(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $brouillon), [
                'payer_reference' => '+237600000001',
                'operator' => 'orange_money',
            ])->assertSessionHasNoErrors();

        // « Autorise » ne suffit pas : la demande ne passe toujours pas.
        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertRedirect(route('citizen.requests.payment', $brouillon));
        $this->assertSame(RequestStatus::Draft, $brouillon->refresh()->status);

        // Fonds acquis.
        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.reconcile', $brouillon))
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(PaymentService::class)->isPaid($brouillon->refresh()));

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('citizen.requests.show', $brouillon));

        $this->assertSame(RequestStatus::Pending, $brouillon->refresh()->status);
    }

    #[Test]
    public function barriere_avant_envoi_un_reglement_refuse_ne_debloque_rien(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $brouillon), [
                'payer_reference' => 'DEMO-REFUS-1',
                'operator' => 'mtn_mobile_money',
            ])->assertSessionHasNoErrors();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertRedirect(route('citizen.requests.payment', $brouillon));

        $this->assertSame(RequestStatus::Draft, $brouillon->refresh()->status);
    }

    /* ----------------------------------------------------- before_signature */

    #[Test]
    public function barriere_avant_signature_l_envoi_reste_libre(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SIGNATURE]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.save', [$brouillon, 4]))
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Pending, $brouillon->refresh()->status);
    }

    #[Test]
    public function barriere_avant_signature_le_maire_ne_signe_pas_une_demande_impayee(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SIGNATURE]);

        $brouillon = $this->brouillonPret();
        $this->actingAs($this->citoyen)->post(route('citizen.requests.save', [$brouillon, 4]));
        $demande = $this->jusquALaSignature($brouillon->refresh());

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $demande))
            ->assertSessionHasErrors('reason');

        $this->assertSame(RequestStatus::AwaitingSignature, $demande->refresh()->status);
        $this->assertNull($demande->signature);
    }

    #[Test]
    public function barriere_avant_signature_le_maire_signe_une_demande_payee(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SIGNATURE]);

        $brouillon = $this->brouillonPret();
        $this->actingAs($this->citoyen)->post(route('citizen.requests.save', [$brouillon, 4]));
        $demande = $this->jusquALaSignature($brouillon->refresh());

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $demande), [
                'payer_reference' => '+237600000001', 'operator' => 'orange_money',
            ]);
        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.reconcile', $demande));

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $demande->refresh()))
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Signed, $demande->refresh()->status);
    }

    /**
     * Le placement qui n'est PAS configure ne doit rien bloquer.
     *
     * C'est le risque propre au fait d'avoir construit les deux : une
     * barriere qui s'appliquerait aux deux endroits doublerait le paiement.
     */
    #[Test]
    public function la_barriere_avant_signature_ne_s_applique_pas_a_l_envoi_et_reciproquement(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SIGNATURE]);
        $gate = app(PaymentGate::class);
        $brouillon = $this->brouillonPret();

        $this->assertTrue($gate->allows($brouillon, PaymentGate::BEFORE_SUBMISSION));
        $this->assertFalse($gate->allows($brouillon, PaymentGate::BEFORE_SIGNATURE));

        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION]);

        $this->assertFalse($gate->allows($brouillon, PaymentGate::BEFORE_SUBMISSION));
        $this->assertTrue($gate->allows($brouillon, PaymentGate::BEFORE_SIGNATURE));
    }

    #[Test]
    public function un_placement_inconnu_echoue_bruyamment(): void
    {
        config(['phoenix.payments.gate' => 'apres_le_cafe']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/inconnu/');

        app(PaymentGate::class)->placement();
    }

    /* ------------------------------------------------------------- le recu */

    #[Test]
    public function le_recu_n_est_servi_que_pour_un_reglement_acquis(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION]);

        $brouillon = $this->brouillonPret();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.payment.receipt', $brouillon))
            ->assertNotFound();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.payment.store', $brouillon), [
                'payer_reference' => '+237600000001', 'operator' => 'orange_money',
            ]);

        // Autorise, pas acquis : toujours pas de recu.
        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.payment.receipt', $brouillon))
            ->assertNotFound();

        $this->actingAs($this->citoyen)->post(route('citizen.requests.payment.reconcile', $brouillon));

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.payment.receipt', $brouillon))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function un_autre_citoyen_n_atteint_ni_l_ecran_ni_le_recu(): void
    {
        config(['phoenix.payments.gate' => PaymentGate::BEFORE_SUBMISSION]);

        $brouillon = $this->brouillonPret();
        $autre = User::factory()->citizen()->create();

        $this->actingAs($autre)->get(route('citizen.requests.payment', $brouillon))->assertNotFound();
        $this->actingAs($autre)->get(route('citizen.requests.payment.receipt', $brouillon))->assertNotFound();
        $this->actingAs($autre)
            ->post(route('citizen.requests.payment.store', $brouillon), [
                'payer_reference' => '+237600000009', 'operator' => 'orange_money',
            ])
            ->assertNotFound();
    }
}
