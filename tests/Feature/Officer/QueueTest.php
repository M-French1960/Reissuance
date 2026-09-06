<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueTest extends TestCase
{
    private CivilStatusCenter $centreA;

    private CivilStatusCenter $centreB;

    private User $officier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->centreA = CivilStatusCenter::factory()->create();
        $this->centreB = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centreA)->create();
    }

    private function demandeEnvoyee(CivilStatusCenter $centre, ?string $nom = null): ReissuanceRequest
    {
        $citoyen = User::factory()->citizen()->create();

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => $nom ?? 'Personne DE TEST',
        ]);
        $demande->forceFill(['submitted_at' => now()])->save();

        app(RequestTransitionService::class)->transition($demande, RequestStatus::Pending, $citoyen);

        return $demande->refresh();
    }

    #[Test]
    public function la_file_ne_montre_que_le_centre_de_l_officier(): void
    {
        $mienne = $this->demandeEnvoyee($this->centreA, 'Demande DU CENTRE A');
        $autre = $this->demandeEnvoyee($this->centreB, 'Demande DU CENTRE B');

        $this->actingAs($this->officier)->get(route('officer.queue'))
            ->assertOk()
            ->assertSee($mienne->reference)
            ->assertDontSee($autre->reference);
    }

    /** Un brouillon n'existe que pour son auteur : jamais dans la file. */
    #[Test]
    public function un_brouillon_n_apparait_jamais_dans_la_file(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $brouillon = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centreA->id,
            'commune_id' => $this->centreA->commune_id,
            'reason' => 'lost',
        ]);

        $this->actingAs($this->officier)->get(route('officer.queue'))
            ->assertOk()
            ->assertDontSee($brouillon->reference);
    }

    #[Test]
    public function la_recherche_filtre_sur_la_reference_et_le_nom(): void
    {
        $cible = $this->demandeEnvoyee($this->centreA, 'Amadou RECHERCHE');
        $autre = $this->demandeEnvoyee($this->centreA, 'Personne AUTRE');

        $this->actingAs($this->officier)
            ->get(route('officer.queue', ['recherche' => 'RECHERCHE']))
            ->assertOk()->assertSee($cible->reference)->assertDontSee($autre->reference);

        $this->actingAs($this->officier)
            ->get(route('officer.queue', ['recherche' => $cible->reference]))
            ->assertOk()->assertSee($cible->reference)->assertDontSee($autre->reference);
    }

    #[Test]
    public function le_filtre_par_assignation_fonctionne(): void
    {
        $libre = $this->demandeEnvoyee($this->centreA);
        $prise = $this->demandeEnvoyee($this->centreA);

        $this->actingAs($this->officier)
            ->post(route('officer.verification.claim', $prise))->assertRedirect();

        $this->actingAs($this->officier)
            ->get(route('officer.queue', ['assignation' => 'moi']))
            ->assertOk()->assertSee($prise->reference)->assertDontSee($libre->reference);

        $this->actingAs($this->officier)
            ->get(route('officer.queue', ['assignation' => 'libre']))
            ->assertOk()->assertSee($libre->reference)->assertDontSee($prise->reference);
    }

    /** Aucune collection non bornée (§8.5). */
    #[Test]
    public function la_file_est_paginee(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->demandeEnvoyee($this->centreA);
        }

        $reponse = $this->actingAs($this->officier)->get(route('officer.queue'))->assertOk();

        $this->assertCount(25, $reponse->viewData('requests')->items());
        $reponse->assertSee('Page 1 sur 2');
    }

    #[Test]
    public function un_tri_invente_dans_l_url_retombe_sur_le_defaut(): void
    {
        $this->demandeEnvoyee($this->centreA);

        $this->actingAs($this->officier)
            ->get(route('officer.queue', ['tri' => 'password', 'sens' => 'peu importe']))
            ->assertOk()
            ->assertViewHas('sort', 'submitted_at')
            ->assertViewHas('direction', 'desc');
    }

    #[Test]
    public function les_autres_roles_n_accedent_pas_a_la_file(): void
    {
        $this->actingAs(User::factory()->citizen()->create())
            ->get(route('officer.queue'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('officer.queue'))->assertForbidden();

        $this->actingAs(User::factory()->mayor($this->centreA->commune)->create())
            ->get(route('officer.queue'))->assertForbidden();
    }
}
