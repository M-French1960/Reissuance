<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Liberer une affectation bloquee (D-057).
 *
 * LE DEFAUT QUE CELA CORRIGE : `claim` exige l'etat « en attente » et aucun
 * agent affecte ; `decide` exige l'etat « en cours d'examen » et l'affectation
 * a soi-meme ; et `assigned_officer_id` n'etait jamais remis a zero. Un
 * dossier dont l'agent affecte ne pouvait plus agir n'etait donc repris par
 * personne — atteignable par une action ordinaire de l'administrateur, et sans
 * la moindre alerte.
 *
 * CE QUE CES TESTS SURVEILLENT AUSSI : que l'ecran ne devienne pas une porte
 * derobee vers les dossiers. L'administrateur n'a acces a aucune donnee
 * d'identite, et cela doit rester vrai.
 */
class AssignmentReleaseTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $admin;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->officier = User::factory()->officer($this->centre)->create();

        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-400000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne TRES-RECONNAISSABLE',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
            'father_name' => 'Père TRES-RECONNAISSABLE',
            'mother_name' => 'Mère TRES-RECONNAISSABLE',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::Pending, $citoyen
        );

        $this->actingAs($this->officier)
            ->post(route('officer.verification.claim', $this->demande));

        $this->demande->refresh();
    }

    private function bloquerParSuspension(): void
    {
        $this->officier->forceFill(['status' => 'suspended'])->save();
    }

    #[Test]
    public function une_affectation_tenue_par_un_agent_actif_n_est_pas_signalee(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertSee('Aucune affectation bloquée')
            ->assertSee($this->demande->reference);
    }

    #[Test]
    public function une_affectation_dont_l_agent_est_suspendu_est_signalee(): void
    {
        $this->bloquerParSuspension();

        $this->actingAs($this->admin)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertSee("Ces dossiers n'avancent plus")
            ->assertSee($this->demande->reference)
            ->assertSee('Compte inactif');
    }

    #[Test]
    public function une_affectation_dont_l_agent_a_change_de_centre_est_signalee(): void
    {
        $this->officier->forceFill([
            'civil_status_center_id' => CivilStatusCenter::factory()->create()->id,
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertSee('Rattaché ailleurs');
    }

    /** Le coeur : liberer rend le dossier reprenable, sans changer son etat. */
    #[Test]
    public function liberer_rend_le_dossier_reprenable_sans_changer_son_etat(): void
    {
        $this->bloquerParSuspension();
        $collegue = User::factory()->officer($this->centre)->create();

        $this->assertFalse($collegue->can('claim', $this->demande), 'Avant : bloqué.');

        $this->actingAs($this->admin)
            ->post(route('admin.assignments.release', $this->demande))
            ->assertRedirect(route('admin.assignments.index'));

        $this->demande->refresh();

        $this->assertNull($this->demande->assigned_officer_id);
        $this->assertSame(RequestStatus::UnderReview, $this->demande->status, "L'état ne doit pas changer.");
        $this->assertTrue($collegue->fresh()->can('claim', $this->demande), 'Après : reprenable.');
    }

    #[Test]
    public function la_liberation_est_tracee_au_journal(): void
    {
        $this->bloquerParSuspension();

        $this->actingAs($this->admin)->post(route('admin.assignments.release', $this->demande));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'request.assignment_released',
            'auditable_type' => 'reissuance_request',
            'auditable_id' => $this->demande->id,
            'actor_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function un_officier_ne_peut_pas_liberer_une_affectation(): void
    {
        $this->actingAs($this->officier)
            ->post(route('admin.assignments.release', $this->demande))
            ->assertForbidden();

        $this->assertNotNull($this->demande->refresh()->assigned_officer_id);
    }

    #[Test]
    public function un_maire_et_un_citoyen_ne_peuvent_ni_voir_ni_liberer(): void
    {
        foreach ([
            User::factory()->mayor($this->centre->commune)->create(),
            User::factory()->citizen()->create(),
        ] as $acteur) {
            $this->actingAs($acteur)->get(route('admin.assignments.index'))->assertForbidden();
            $this->actingAs($acteur)->post(route('admin.assignments.release', $this->demande))->assertForbidden();
        }
    }

    /**
     * L'ecran ne doit JAMAIS laisser filtrer une donnee d'identite.
     *
     * C'est la contrepartie du contournement de portee : l'administrateur voit
     * des affectations, pas des dossiers.
     */
    #[Test]
    public function l_ecran_ne_montre_aucune_donnee_d_identite(): void
    {
        $this->bloquerParSuspension();

        $reponse = $this->actingAs($this->admin)->get(route('admin.assignments.index'))->assertOk();

        $reponse->assertDontSee('TRES-RECONNAISSABLE');
        $reponse->assertDontSee('15 janvier 1990');
        $reponse->assertDontSee('Ville de test');
    }
}
