<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que la vérification permet quand elle n'aboutit pas.
 *
 * docs/INTEGRATIONS.md 6 promet qu'une panne ne bloque pas le service :
 * `unavailable` est un résultat enregistré, donc les quatre vérifications
 * peuvent être complètes malgré une panne, et l'officier décide en connaissance
 * de cause. Ces tests constatent le comportement réel, y compris là où il est
 * plus permissif qu'on ne l'imagine.
 */
class DegradedVerificationTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();

        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-800000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
        $this->demande->refresh();
    }

    /** @param array<int, VerificationResult> $resultats */
    private function enregistre(array $resultats): void
    {
        $workflow = app(VerificationWorkflow::class);

        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record(
                $this->demande, $n, $this->officier,
                $resultats[$n] ?? VerificationResult::Match
            );
        }

        $this->demande->refresh();
    }

    /**
     * Une panne ne bloque pas le service : c'est la promesse d'INTEGRATIONS 6.
     *
     * Elle exige en revanche un motif, comme toute acceptation sous reserve.
     */
    #[Test]
    public function une_indisponibilite_est_un_resultat_et_ne_bloque_pas_la_decision(): void
    {
        $this->enregistre([2 => VerificationResult::ProviderUnavailable]);

        $this->assertTrue(app(VerificationWorkflow::class)->isComplete($this->demande));

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), [
                'decision' => 'accepted',
                'reason' => 'Piece presentee au guichet et verifiee a la main : la base de la police est injoignable.',
            ])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
    }

    /** L'escalade au maire reste ouverte pendant une panne (T6). */
    #[Test]
    public function l_escalade_reste_ouverte_pendant_une_panne(): void
    {
        $this->enregistre([2 => VerificationResult::ProviderUnavailable]);

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), [
                'decision' => 'escalated',
                'reason' => 'La base de la police est injoignable depuis ce matin.',
            ])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Escalated, $this->demande->refresh()->status);
    }

    /**
     * Un « aucune correspondance » de la base de la police n'empeche pas
     * l'acceptation — mais il ne peut plus passer en silence.
     *
     * L'officier garde son pouvoir de decision : c'est ce que dit
     * INTEGRATIONS 6, et le retirer reviendrait a faire decider la machine.
     * Mais c'est le chemin exact d'une fraude a la piece volee, et une telle
     * acceptation ne doit pas ressembler a une acceptation ordinaire. Le motif
     * devient donc obligatoire, et le maire le lit (D-031).
     */
    #[Test]
    public function une_acceptation_sous_reserve_exige_un_motif(): void
    {
        $this->enregistre([2 => VerificationResult::NoMatch]);

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => 'accepted'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(RequestStatus::UnderReview, $this->demande->refresh()->status);
    }

    #[Test]
    public function une_acceptation_sous_reserve_motivee_passe_et_le_motif_est_conserve(): void
    {
        $this->enregistre([2 => VerificationResult::NoMatch]);

        $motif = 'Le numero avait ete saisi avec une faute ; piece originale verifiee au guichet.';

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), [
                'decision' => 'accepted',
                'reason' => $motif,
            ])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
        $this->assertSame($motif, $this->demande->decisions()->latest('id')->firstOrFail()->reason);
    }

    /** Sans reserve, une acceptation reste sans motif obligatoire (8.2). */
    #[Test]
    public function une_acceptation_ordinaire_ne_reclame_toujours_aucun_motif(): void
    {
        $this->enregistre([]);

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => 'accepted'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
    }

    /**
     * Ce que le maire voit du dossier qu'on lui présente à signer.
     *
     * Si l'écran de signature ne montre pas les résultats de vérification, le
     * maire signe à l'aveugle une acceptation faite malgré une non
     * correspondance.
     */
    #[Test]
    public function l_ecran_du_maire_montre_les_resultats_de_verification(): void
    {
        $maire = User::factory()->mayor($this->centre->commune)->create();

        $this->enregistre([
            2 => VerificationResult::NoMatch,
            4 => VerificationResult::ProviderUnavailable,
        ]);

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );

        $this->actingAs($maire)
            ->get(route('mayor.review', $this->demande->refresh()))
            ->assertOk()
            ->assertSee(VerificationResult::NoMatch->label())
            ->assertSee(VerificationResult::ProviderUnavailable->label());
    }
}
