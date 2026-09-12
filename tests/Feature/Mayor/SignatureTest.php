<?php

declare(strict_types=1);

namespace Tests\Feature\Mayor;

use App\Contracts\SignatureProvider;
use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Integrations\Fake\FakeSignatureProvider;
use App\Models\CivilStatusCenter;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConfirmsSignature;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    use ConfirmsSignature;
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $maire;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
        $this->officier = User::factory()->officer($this->centre)->create();

        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-500000001', 'completed_at' => now(),
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
            'father_name' => 'Père DE TEST', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
    }

    private function completeVerification(): void
    {
        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 2, 3, 4, 5] as $n) {
            $workflow->record($this->demande, $n, $this->officier, VerificationResult::Match);
        }
        $this->demande->refresh();
    }

    private function toAwaitingSignature(): void
    {
        $this->completeVerification();
        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );
        // Le maire signe un PROJET établi par l'officier (D-064) : le
        // contrôleur de l'officier le rédige en même temps qu'il décide.
        $this->redigeLeProjet($this->demande, $this->officier);
        $this->demande->refresh();
    }

    private function toEscalated(): void
    {
        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::Escalated, $this->officier, 'Doute sur la pièce présentée.'
        );
        $this->redigeLeProjet($this->demande, $this->officier);
        $this->demande->refresh();
    }

    /** T7 : le maire signe un dossier prêt. */
    #[Test]
    public function t7_le_maire_signe_un_dossier_pret(): void
    {
        $this->toAwaitingSignature();

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $this->demande), [
                'confirmation_code' => $this->codeDeConfirmation($this->maire),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mayor.dashboard'));

        $this->demande->refresh();
        $this->assertSame(RequestStatus::Signed, $this->demande->status);

        $signature = $this->demande->signature;
        $this->assertNotNull($signature);
        $this->assertSame($this->maire->id, $signature->mayor_id);
        $this->assertFalse($signature->legally_binding);
        Storage::disk('private')->assertExists($signature->document_path);
        Storage::disk('private')->assertExists($signature->proof_path);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $signature->id, 'action' => 'act.issued',
        ]);
        $this->assertDatabaseHas('request_decisions', [
            'request_id' => $this->demande->id, 'decision' => 'signed',
            'from_status' => 'awaiting_signature', 'to_status' => 'signed',
        ]);
    }

    /**
     * Le §4.3 du brief : aucun chemin ne produit un acte signé sans
     * vérification complète. La règle s'applique AUSSI au maire.
     */
    #[Test]
    public function signer_sans_verification_complete_est_refuse(): void
    {
        // On atteint awaiting_signature en contournant la barrière de
        // l'officier, pour vérifier que celle du maire tient seule.
        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $this->demande->refresh()))
            ->assertSessionHasErrors('reason');

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
        $this->assertNull($this->demande->signature);
        $this->assertSame(0, DocumentSignature::count());
    }

    /** T9 : approbation par exception, motif obligatoire. */
    #[Test]
    public function t9_l_approbation_par_exception_exige_un_motif(): void
    {
        $this->completeVerification();
        $this->toEscalated();

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $this->demande))
            ->assertSessionHasErrors('reason');
        $this->assertSame(RequestStatus::Escalated, $this->demande->refresh()->status);

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $this->demande), [
                'reason' => 'Pièce complémentaire présentée en mairie et vérifiée ce jour.',
                'confirmation_code' => $this->codeDeConfirmation($this->maire),
            ])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Signed, $this->demande->refresh()->status);
        $this->assertDatabaseHas('request_decisions', [
            'request_id' => $this->demande->id,
            'decision' => 'approved_by_exception',
        ]);
    }

    /** T10 : rejet d'une escalade. */
    #[Test]
    public function t10_le_maire_rejette_une_escalade_avec_motif(): void
    {
        $this->toEscalated();

        $this->actingAs($this->maire)
            ->post(route('mayor.reject', $this->demande))
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->maire)
            ->post(route('mayor.reject', $this->demande), [
                'reason' => "Les éléments fournis ne permettent pas d'établir l'identité.",
            ])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Rejected, $this->demande->refresh()->status);
    }

    /** T8 : retour à l'officier depuis « prêt à signer » — option A validée. */
    #[Test]
    public function t8_le_retour_a_l_officier_ouvre_un_nouveau_cycle(): void
    {
        $this->toAwaitingSignature();
        $cycleAvant = $this->demande->verification_cycle;

        $this->actingAs($this->maire)
            ->post(route('mayor.return', $this->demande), [
                'reason' => 'La photographie de la pièce est trop floue pour conclure.',
            ])->assertSessionHasNoErrors();

        $this->demande->refresh();
        $this->assertSame(RequestStatus::UnderReview, $this->demande->status);
        $this->assertSame($cycleAvant + 1, $this->demande->verification_cycle);

        // Les étapes du cycle précédent sont CONSERVÉES intactes.
        $this->assertSame(5, $this->demande->verificationSteps()->where('cycle', $cycleAvant)->count());
        // Et le nouveau cycle repart vide.
        $workflow = app(VerificationWorkflow::class);
        $this->assertFalse($workflow->isComplete($this->demande));
        $this->assertSame([1, 2, 3, 4], $workflow->missingSteps($this->demande));
    }

    #[Test]
    public function le_retour_a_l_officier_exige_un_motif(): void
    {
        $this->toAwaitingSignature();

        $this->actingAs($this->maire)
            ->post(route('mayor.return', $this->demande))
            ->assertSessionHasErrors('reason');

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
    }

    #[Test]
    public function un_maire_d_une_autre_commune_n_atteint_pas_le_dossier(): void
    {
        $this->toAwaitingSignature();
        $autre = User::factory()->mayor(CivilStatusCenter::factory()->create()->commune)->create();

        $this->actingAs($autre)->get(route('mayor.review', $this->demande))->assertNotFound();
        $this->actingAs($autre)->post(route('mayor.sign', $this->demande))->assertNotFound();
    }

    /**
     * Aucun autre rôle ne signe — mais le code de refus diffère, et c'est
     * volontaire.
     *
     * L'officier VOIT le dossier (son centre, hors brouillon) : la liaison de
     * modèle réussit, et c'est le middleware de rôle qui refuse — 403.
     *
     * L'administrateur et le citoyen ne le voient pas du tout : la portée
     * globale bloque dès la liaison, et la réponse ne confirme même pas
     * l'existence du dossier — 404. C'est mieux qu'un 403.
     */
    #[Test]
    public function les_autres_roles_ne_signent_pas(): void
    {
        $this->toAwaitingSignature();

        $this->actingAs($this->officier)
            ->post(route('mayor.sign', $this->demande))
            ->assertForbidden();

        foreach ([User::factory()->admin()->create(), User::factory()->citizen()->create()] as $acteur) {
            $this->actingAs($acteur)
                ->post(route('mayor.sign', $this->demande))
                ->assertNotFound();
        }

        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
        $this->assertSame(0, DocumentSignature::count());
    }

    /**
     * Un état terminal ne se resigne pas.
     *
     * Une fois signée, la demande sort de la portée du maire — qui ne voit
     * que `awaiting_signature` et `escalated`. Le refus est donc un 404 : le
     * dossier n'existe plus pour lui. Le déclencheur MySQL refuserait de
     * toute façon toute sortie d'un état terminal.
     */
    #[Test]
    public function un_acte_deja_signe_ne_peut_pas_etre_resigne(): void
    {
        $this->toAwaitingSignature();
        $this->actingAs($this->maire)->post(route('mayor.sign', $this->demande), [
            'confirmation_code' => $this->codeDeConfirmation($this->maire),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $this->demande->refresh()))
            ->assertNotFound();

        $this->assertSame(1, DocumentSignature::count());
        $this->assertSame(RequestStatus::Signed, $this->demande->refresh()->status);
    }

    #[Test]
    public function l_adaptateur_factice_est_le_defaut_et_declare_ne_pas_engager(): void
    {
        $this->assertInstanceOf(FakeSignatureProvider::class, app(SignatureProvider::class));

        $resultat = app(SignatureProvider::class)->sign('%PDF-1.4', ['reference' => 'TEST']);

        $this->assertFalse($resultat->legallyBinding);
        $this->assertFalse($resultat->proof['legally_binding']);
        $this->assertSame(hash('sha256', '%PDF-1.4'), $resultat->documentHash);
    }
}
