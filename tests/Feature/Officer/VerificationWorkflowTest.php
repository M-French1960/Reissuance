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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerificationWorkflowTest extends TestCase
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
            'national_id_number' => 'DEMO-100000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::Pending, $citoyen
        );
    }

    private function prendEnCharge(): void
    {
        $this->actingAs($this->officier)
            ->post(route('officer.verification.claim', $this->demande))
            ->assertRedirect();
        $this->demande->refresh();
    }

    /** Transition T3 : prise en charge. */
    #[Test]
    public function un_officier_prend_une_demande_en_charge(): void
    {
        $this->prendEnCharge();

        $this->assertSame(RequestStatus::UnderReview, $this->demande->status);
        $this->assertSame($this->officier->id, $this->demande->assigned_officer_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $this->demande->id,
            'from_status' => 'pending', 'to_status' => 'under_review',
        ]);
    }

    /** R4 : un collègue ne reprend pas un dossier déjà pris. */
    #[Test]
    public function une_demande_deja_prise_ne_peut_pas_etre_reprise(): void
    {
        $this->prendEnCharge();
        $collegue = User::factory()->officer($this->centre)->create();

        $this->actingAs($collegue)
            ->post(route('officer.verification.claim', $this->demande))
            ->assertForbidden();
    }

    /**
     * R5 de docs/PERMISSIONS.md — le dernier refus qui restait en attente.
     *
     * C'est la barrière anti-fraude centrale : on n'accepte pas un dossier
     * dont la vérification n'a pas été menée.
     */
    #[Test]
    public function r5_accepter_sans_les_cinq_etapes_est_refuse(): void
    {
        $this->prendEnCharge();

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => 'accepted'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(RequestStatus::UnderReview, $this->demande->refresh()->status);
        $this->assertSame(0, $this->demande->decisions()->count());
    }

    /** Même avec quatre étapes sur cinq : toujours refusé. */
    #[Test]
    public function r5bis_quatre_etapes_sur_cinq_ne_suffisent_pas(): void
    {
        $this->prendEnCharge();
        $workflow = app(VerificationWorkflow::class);

        foreach ([1, 2, 3, 4] as $etape) {
            $workflow->record($this->demande, $etape, $this->officier, VerificationResult::Match);
        }

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => 'accepted'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(RequestStatus::UnderReview, $this->demande->refresh()->status);
    }

    #[Test]
    public function avec_les_cinq_etapes_l_acceptation_passe(): void
    {
        $this->prendEnCharge();
        $workflow = app(VerificationWorkflow::class);

        foreach ([1, 2, 3, 4, 5] as $etape) {
            $workflow->record($this->demande, $etape, $this->officier, VerificationResult::Match);
        }

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => 'accepted'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
        $this->assertDatabaseHas('request_decisions', [
            'request_id' => $this->demande->id,
            'decision' => 'accepted',
            'from_status' => 'under_review',
            'to_status' => 'awaiting_signature',
        ]);
    }

    /**
     * Une panne du service externe ne bloque pas l'officier (§9 du brief) :
     * `provider_unavailable` EST un résultat, ce qui débloque la situation
     * sans masquer que la vérification n'a pas abouti.
     */
    #[Test]
    public function une_panne_externe_ne_bloque_pas_la_verification(): void
    {
        $this->demande->citizen->profile->update(['national_id_number' => 'DEMO-DOWN-42']);
        $this->prendEnCharge();

        $this->actingAs($this->officier)
            ->post(route('officer.verification.identity', $this->demande))
            ->assertSessionHasNoErrors();

        $etape = $this->demande->verificationSteps()->where('step', 2)->firstOrFail();
        $this->assertSame(VerificationResult::ProviderUnavailable, $etape->result);

        // L'étape compte comme renseignée : l'acceptation redevient possible
        // une fois les autres étapes faites.
        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 3, 4, 5] as $n) {
            $workflow->record($this->demande, $n, $this->officier, VerificationResult::Match);
        }
        $this->assertTrue($workflow->isComplete($this->demande->refresh()));
    }

    #[Test]
    #[DataProvider('decisionsMotivables')]
    public function un_rejet_ou_une_escalade_exige_un_motif(string $decision, string $statutAttendu): void
    {
        $this->prendEnCharge();

        // Sans motif : refusé.
        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => $decision])
            ->assertSessionHasErrors('reason');
        $this->assertSame(RequestStatus::UnderReview, $this->demande->refresh()->status);

        // Motif trop court : refusé aussi.
        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), ['decision' => $decision, 'reason' => 'non'])
            ->assertSessionHasErrors('reason');

        // Motif explicite : accepté.
        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), [
                'decision' => $decision,
                'reason' => 'Les photographies ne correspondent pas à la pièce fournie.',
            ])->assertSessionHasNoErrors();

        $this->assertSame($statutAttendu, $this->demande->refresh()->status->value);
    }

    /** @return iterable<string, array{string, string}> */
    public static function decisionsMotivables(): iterable
    {
        yield 'rejet' => ['rejected', 'rejected'];
        yield 'escalade' => ['escalated', 'escalated'];
    }

    /** Le rejet et l'escalade restent possibles même vérification incomplète. */
    #[Test]
    public function rejeter_reste_possible_sans_les_cinq_etapes(): void
    {
        $this->prendEnCharge();

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $this->demande), [
                'decision' => 'rejected',
                'reason' => "La pièce d'identité fournie est illisible.",
            ])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Rejected, $this->demande->refresh()->status);
    }

    /** Seul l'officier assigné décide ; un collègue consulte seulement. */
    #[Test]
    public function un_collegue_ne_peut_pas_decider_a_la_place_de_l_assigne(): void
    {
        $this->prendEnCharge();
        $collegue = User::factory()->officer($this->centre)->create();

        $this->actingAs($collegue)
            ->post(route('officer.decision.store', $this->demande), [
                'decision' => 'rejected', 'reason' => 'Motif suffisamment long pour passer.',
            ])->assertForbidden();

        // Mais il peut consulter le dossier : entraide et continuité de service.
        $this->actingAs($collegue)
            ->get(route('officer.verification.step', ['reissuanceRequest' => $this->demande, 'step' => 1]))
            ->assertOk()
            ->assertSee('Lecture seule');
    }

    #[Test]
    public function un_officier_d_un_autre_centre_n_atteint_pas_le_dossier(): void
    {
        $this->prendEnCharge();
        $autre = User::factory()->officer(CivilStatusCenter::factory()->create())->create();

        // La portée globale empêche jusqu'à la liaison de modèle : 404, ce qui
        // ne confirme même pas l'existence du dossier.
        $this->actingAs($autre)
            ->get(route('officer.verification.step', ['reissuanceRequest' => $this->demande, 'step' => 1]))
            ->assertNotFound();
    }

    /** Les étapes survivent à une interruption : elles sont persistées. */
    #[Test]
    public function une_verification_interrompue_est_reprenable(): void
    {
        $this->prendEnCharge();

        $this->actingAs($this->officier)->post(
            route('officer.verification.acknowledge', ['reissuanceRequest' => $this->demande, 'step' => 1]),
            ['result' => 'match', 'note' => 'Informations cohérentes.']
        )->assertRedirect();

        // Nouvelle session : le résultat est toujours là.
        $etapes = app(VerificationWorkflow::class)->steps($this->demande->refresh());
        $this->assertSame(VerificationResult::Match, $etapes->get(1)?->result);
        $this->assertSame([2, 3, 4, 5], app(VerificationWorkflow::class)->missingSteps($this->demande));
    }
}
