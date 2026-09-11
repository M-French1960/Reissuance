<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le bandeau de l'ecran de verification doit dire la VERITE.
 *
 * POURQUOI CE FICHIER EXISTE. Le bandeau repliait sur « Ce dossier est pris en
 * charge par un autre agent » des que l'officier ne pouvait pas decider — y
 * compris quand PERSONNE ne l'avait pris en charge. Il affirmait une chose
 * fausse et masquait la seule action possible : prendre le dossier en charge.
 * L'agent repartait vers un autre dossier pour rien.
 *
 * Les tests existants verifiaient que l'etat lecture seule BLOQUE la decision —
 * ce qui etait vrai. Aucun ne verifiait que le message soit exact. C'est le
 * defaut typique qu'une suite verte ne voit pas et qu'un coup d'oeil a l'ecran
 * revele (D-055).
 */
class VerificationBannerTest extends TestCase
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
            'national_id_number' => 'DEMO-200000001', 'completed_at' => now(),
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

    private function ecran(User $agent): TestResponse
    {
        return $this->actingAs($agent)->get(
            route('officer.verification.step', ['reissuanceRequest' => $this->demande, 'step' => 1])
        );
    }

    /** Dossier libre : on propose de le prendre, on n'invente pas un occupant. */
    #[Test]
    public function un_dossier_que_personne_ne_traite_propose_la_prise_en_charge(): void
    {
        $this->ecran($this->officier)
            ->assertOk()
            ->assertSee('Dossier à prendre en charge')
            ->assertSee('Prendre en charge')
            ->assertSee(route('officer.verification.claim', $this->demande))
            ->assertDontSee('pris en charge par');
    }

    /** Dossier tenu par un collegue : on le NOMME, et on n'offre pas l'action. */
    #[Test]
    public function un_dossier_tenu_par_un_collegue_le_nomme(): void
    {
        $collegue = User::factory()->officer($this->centre)->create(['name' => 'Agent OCCUPANT']);

        $this->actingAs($collegue)
            ->post(route('officer.verification.claim', $this->demande))
            ->assertRedirect();

        $this->ecran($this->officier)
            ->assertOk()
            ->assertSee('Lecture seule')
            ->assertSee('Agent OCCUPANT')
            ->assertDontSee('un autre agent')
            ->assertDontSee('Dossier à prendre en charge');
    }

    /**
     * Dossier en cours d'examen sans agent affecte : etat sans issue.
     *
     * Ni `claim` (qui exige l'etat « en attente ») ni `decide` (qui exige une
     * affectation) ne s'appliquent. Le bandeau doit le dire, et renvoyer vers
     * l'administrateur — pas pretendre qu'un collegue s'en occupe.
     */
    #[Test]
    public function un_dossier_en_examen_sans_agent_affecte_le_dit_et_ne_ment_pas(): void
    {
        $collegue = User::factory()->officer($this->centre)->create();

        $this->actingAs($collegue)
            ->post(route('officer.verification.claim', $this->demande))
            ->assertRedirect();

        // L'affectation disparait : c'est l'etat qu'on obtient quand l'agent
        // affecte n'est plus en mesure d'agir.
        $this->demande->refresh()->forceFill(['assigned_officer_id' => null])->save();

        $this->ecran($this->officier)
            ->assertOk()
            ->assertSee('Dossier sans agent affecté')
            ->assertDontSee('un autre agent')
            ->assertDontSee('Dossier à prendre en charge');
    }

    /** L'agent qui tient le dossier ne voit aucun bandeau de blocage. */
    #[Test]
    public function l_agent_qui_tient_le_dossier_ne_voit_aucun_bandeau(): void
    {
        $this->actingAs($this->officier)
            ->post(route('officer.verification.claim', $this->demande))
            ->assertRedirect();

        $this->ecran($this->officier)
            ->assertOk()
            ->assertDontSee('Lecture seule')
            ->assertDontSee('Dossier à prendre en charge')
            ->assertDontSee('Dossier sans agent affecté');
    }
}
