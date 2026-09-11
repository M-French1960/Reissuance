<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\Commune;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * Champs surnumeraires : ce que le client ajoute au formulaire ne doit rien
 * changer.
 *
 * Tous les controleurs partent de $request->validate(), qui ne rend que les
 * cles connues. C'est solide — mais c'est une propriete du code, pas une
 * garantie du framework : un `$request->all()` glisse dans un correctif la
 * ferait tomber sans bruit. Ces tests postent donc, en plus des champs
 * legitimes, ceux qui feraient basculer un dossier ou un compte.
 */
class MassAssignmentTest extends TestCase
{
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $autreCitoyen;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->autreCitoyen = User::factory()->citizen()->create();

        foreach ([$this->citoyen, $this->autreCitoyen] as $i => $u) {
            $u->profile()->create([
                'first_name' => 'Personne', 'last_name' => 'DE TEST',
                'national_id_number' => 'DEMO-70000000'.$i, 'completed_at' => now(),
            ]);
        }
    }

    private function brouillon(): ReissuanceRequest
    {
        $this->actingAs($this->citoyen)->post(route('citizen.requests.start'));

        return ReissuanceRequest::withoutGlobalScopes()
            ->where('user_id', $this->citoyen->id)->latest('id')->firstOrFail();
    }

    /**
     * L'assistant citoyen : le brouillon ne change ni de proprietaire, ni de
     * reference, ni d'etat, ni d'avancement.
     */
    #[Test]
    public function l_assistant_ignore_les_champs_qui_ne_sont_pas_de_l_etape(): void
    {
        $brouillon = $this->brouillon();
        $reference = $brouillon->reference;

        $this->actingAs($this->citoyen)->post(route('citizen.requests.save', [$brouillon, 1]), [
            'reason' => 'lost',
            'copies_requested' => 1,
            // Les intrus.
            'user_id' => $this->autreCitoyen->id,
            'reference' => 'PHX-XXXX-XXXX',
            'status' => RequestStatus::Signed->value,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'last_completed_step' => 4,
            'submitted_at' => now()->toDateTimeString(),
            'verification_cycle' => 9,
            'assigned_officer_id' => 1,
        ])->assertSessionHasNoErrors();

        $brouillon->refresh();

        $this->assertSame($this->citoyen->id, $brouillon->user_id);
        $this->assertSame($reference, $brouillon->reference);
        $this->assertSame(RequestStatus::Draft, $brouillon->status);
        $this->assertSame(1, $brouillon->last_completed_step);
        $this->assertNull($brouillon->civil_status_center_id);
        $this->assertNull($brouillon->commune_id);
        $this->assertNull($brouillon->submitted_at);
        $this->assertNull($brouillon->assigned_officer_id);
    }

    /**
     * La commune est deduite du centre, jamais recue.
     *
     * Elle designe le maire competent : la choisir reviendrait a choisir qui
     * signe l'acte.
     */
    #[Test]
    public function la_commune_ne_peut_pas_etre_choisie_par_le_citoyen(): void
    {
        $brouillon = $this->brouillon();
        $autreCommune = Commune::factory()->create();

        $this->actingAs($this->citoyen)->post(route('citizen.requests.save', [$brouillon, 1]), [
            'reason' => 'lost', 'copies_requested' => 1,
        ]);
        $this->actingAs($this->citoyen)->post(route('citizen.requests.save', [$brouillon, 2]), [
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);

        $this->actingAs($this->citoyen)->post(route('citizen.requests.save', [$brouillon, 3]), [
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $autreCommune->id,
        ])->assertSessionHasNoErrors();

        $brouillon->refresh();

        $this->assertSame($this->centre->commune_id, $brouillon->commune_id);
        $this->assertNotSame($autreCommune->id, $brouillon->commune_id);
    }

    #[Test]
    public function le_profil_ne_peut_pas_etre_rattache_a_un_autre_compte(): void
    {
        $this->actingAs($this->citoyen)->patch(route('citizen.profile.update'), [
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'birth_date' => '1990-01-15', 'birth_place' => 'Yaoundé',
            'phone' => '+237 6 00 00 00 01', 'address' => 'Adresse de test',
            'user_id' => $this->autreCitoyen->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            $this->citoyen->id,
            $this->citoyen->profile()->first()->user_id
        );
    }

    /**
     * L'officier decide ; il ne redige pas la ligne de decision.
     *
     * Le statut cible, l'auteur et son role sont derives du serveur. Sans
     * quoi un officier ecrirait « signe par le maire » dans le dossier.
     */
    #[Test]
    public function l_officier_ne_choisit_ni_le_statut_cible_ni_l_auteur(): void
    {
        $officier = User::factory()->officer($this->centre)->create();
        $demande = $this->demandeSousVerification($officier);

        $workflow = app(VerificationWorkflow::class);
        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record($demande, $n, $officier, VerificationResult::Match);
        }

        $this->actingAs($officier)->post(route('officer.decision.store', $demande), [
            'decision' => 'accepted',
            'to_status' => RequestStatus::Signed->value,
            'from_status' => RequestStatus::Draft->value,
            'actor_id' => $this->citoyen->id,
            'actor_role' => UserRole::Mayor->value,
            'request_id' => 999999,
        ])->assertSessionHasNoErrors();

        $demande->refresh();
        $this->assertSame(RequestStatus::AwaitingSignature, $demande->status);

        $decision = $demande->decisions()->latest('id')->firstOrFail();
        $this->assertSame($officier->id, $decision->actor_id);
        $this->assertSame(UserRole::Officer, $decision->actor_role);
        $this->assertSame(RequestStatus::UnderReview->value, $decision->from_status);
        $this->assertSame(RequestStatus::AwaitingSignature->value, $decision->to_status);
    }

    /**
     * Le maire ne peut pas declarer son acte juridiquement contraignant.
     *
     * C'est le fournisseur de signature qui le dit, et le fournisseur factice
     * dit non (D-025). Un champ poste ne doit pas retourner cette reponse.
     */
    #[Test]
    public function le_maire_ne_declare_pas_lui_meme_la_valeur_juridique(): void
    {
        $officier = User::factory()->officer($this->centre)->create();
        $maire = User::factory()->mayor($this->centre->commune)->create();
        $demande = $this->demandeSousVerification($officier);

        $workflow = app(VerificationWorkflow::class);
        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record($demande, $n, $officier, VerificationResult::Match);
        }
        app(RequestTransitionService::class)->transition($demande, RequestStatus::AwaitingSignature, $officier);

        // Le maire signe un PROJET établi par l'officier (D-064).
        $this->redigeLeProjet($demande, $officier);

        $this->actingAs($maire)->post(route('mayor.sign', $demande->refresh()), [
            'legally_binding' => 1,
            'document_hash' => str_repeat('0', 64),
            'provider' => 'autorite-de-certification-inventee',
            'mayor_id' => $this->citoyen->id,
        ])->assertSessionHasNoErrors();

        $signature = $demande->refresh()->signature;

        $this->assertNotNull($signature);
        $this->assertFalse($signature->legally_binding);
        $this->assertSame($maire->id, $signature->mayor_id);
        $this->assertNotSame(str_repeat('0', 64), $signature->document_hash);
        $this->assertNotSame('autorite-de-certification-inventee', $signature->provider);
    }

    /**
     * Un administrateur ne se donne pas un rattachement par le formulaire de
     * changement de statut.
     */
    #[Test]
    public function le_changement_de_statut_ne_permet_pas_de_changer_de_role(): void
    {
        $admin = User::factory()->admin()->create();
        $officier = User::factory()->officer($this->centre)->create();

        $this->actingAs($admin)->patch(route('admin.users.status', $officier), [
            'status' => 'suspended',
            'reason' => 'Motif de test suffisamment explicite.',
            'role' => UserRole::Admin->value,
            'civil_status_center_id' => null,
            'email' => 'change@phoenix.test',
        ])->assertSessionHasNoErrors();

        $officier->refresh();

        $this->assertSame(UserRole::Officer, $officier->role);
        $this->assertSame($this->centre->id, $officier->civil_status_center_id);
        $this->assertNotSame('change@phoenix.test', $officier->email);
    }

    private function demandeSousVerification(User $officier): ReissuanceRequest
    {
        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
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
        $demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($demande, RequestStatus::UnderReview, $officier);
        $demande->forceFill(['assigned_officer_id' => $officier->id])->save();

        return $demande->refresh();
    }
}
