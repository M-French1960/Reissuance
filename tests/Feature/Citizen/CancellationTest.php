<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * « Cancel Request » du diagramme de cas d'utilisation.
 *
 * T13 (draft → cancelled) et T14 (pending → cancelled), reservees au
 * demandeur, et seulement tant que personne n'a pris le dossier en charge.
 *
 * L'annulation n'est PAS une suppression : la demande reste en base avec sa
 * trace. Une demande effacee, c'est un audit anti-fraude qui ne peut plus
 * reconstituer qu'elle a existe.
 */
class CancellationTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $citoyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-340000001', 'completed_at' => now(),
        ]);
    }

    private function demande(RequestStatus $etat = RequestStatus::Draft): ReissuanceRequest
    {
        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);

        if ($etat !== RequestStatus::Draft) {
            $demande->forceFill(['submitted_at' => now()])->save();
            app(RequestTransitionService::class)
                ->transition($demande, RequestStatus::Pending, $this->citoyen);
        }

        return $demande->refresh();
    }

    #[Test]
    public function t13_le_demandeur_annule_son_brouillon(): void
    {
        $demande = $this->demande(RequestStatus::Draft);

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.cancel', $demande), ['reason' => 'Erreur de saisie.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('citizen.requests.index'));

        $this->assertSame(RequestStatus::Cancelled, $demande->refresh()->status);
    }

    #[Test]
    public function t14_le_demandeur_annule_une_demande_envoyee_non_prise_en_charge(): void
    {
        $demande = $this->demande(RequestStatus::Pending);

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.cancel', $demande))
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Cancelled, $demande->refresh()->status);
    }

    /** Une annulation n'efface rien : la demande et sa trace restent. */
    #[Test]
    public function une_annulation_conserve_la_demande_et_sa_trace(): void
    {
        $demande = $this->demande(RequestStatus::Pending);

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.cancel', $demande), ['reason' => 'Je change de centre.']);

        $this->assertDatabaseHas('reissuance_requests', [
            'id' => $demande->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'reissuance_request',
            'auditable_id' => $demande->id,
            'action' => 'request.pending_to_cancelled',
            'reason' => 'Je change de centre.',
        ]);
    }

    /**
     * Une fois le dossier pris en charge, l'annulation n'est plus offerte.
     *
     * Annuler jetterait le travail deja fait par un officier. Le demandeur
     * passe alors par « Contact Officer » — l'autre cas du diagramme.
     */
    #[Test]
    public function un_dossier_pris_en_charge_ne_s_annule_plus(): void
    {
        $demande = $this->demande(RequestStatus::Pending);
        $officier = User::factory()->officer($this->centre)->create();

        app(RequestTransitionService::class)
            ->transition($demande, RequestStatus::UnderReview, $officier);
        $demande->forceFill(['assigned_officer_id' => $officier->id])->save();

        $this->actingAs($this->citoyen)
            ->post(route('citizen.requests.cancel', $demande->refresh()))
            ->assertForbidden();

        $this->assertSame(RequestStatus::UnderReview, $demande->refresh()->status);
    }

    #[Test]
    public function un_autre_citoyen_n_annule_pas_ma_demande(): void
    {
        $demande = $this->demande(RequestStatus::Pending);
        $autre = User::factory()->citizen()->create();

        // 404 et non 403 : la portee globale empeche jusqu'a la liaison de
        // modele, ce qui ne confirme meme pas l'existence du dossier.
        $this->actingAs($autre)
            ->post(route('citizen.requests.cancel', $demande))
            ->assertNotFound();

        $this->assertSame(RequestStatus::Pending, $demande->refresh()->status);
    }

    /**
     * Aucun agent n'annule a la place du demandeur.
     *
     * Le code de refus differe selon l'acteur, et c'est voulu : l'officier
     * VOIT la demande de son centre, la liaison de modele aboutit, et c'est la
     * Policy qui refuse — 403. Le maire et l'administrateur ne la voient meme
     * pas (portee globale) : 404, ce qui ne confirme pas son existence.
     */
    #[Test]
    public function ni_l_officier_ni_le_maire_n_annulent_a_la_place_du_demandeur(): void
    {
        $demande = $this->demande(RequestStatus::Pending);

        $attendus = [
            [User::factory()->officer($this->centre)->create(), 403],
            [User::factory()->mayor($this->centre->commune)->create(), 404],
            [User::factory()->admin()->create(), 404],
        ];

        foreach ($attendus as [$acteur, $code]) {
            $this->actingAs($acteur)
                ->post(route('citizen.requests.cancel', $demande))
                ->assertStatus($code);
        }

        $this->assertSame(RequestStatus::Pending, $demande->refresh()->status);
    }

    /** `cancelled` est terminal : on n'en sort pas, meme par le service. */
    #[Test]
    public function une_demande_annulee_ne_repart_pas(): void
    {
        $demande = $this->demande(RequestStatus::Pending);

        $this->actingAs($this->citoyen)->post(route('citizen.requests.cancel', $demande));

        $officier = User::factory()->officer($this->centre)->create();

        $this->expectException(DomainException::class);

        app(RequestTransitionService::class)
            ->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
    }

    /** Et la base refuse aussi, si le service etait contourne. */
    #[Test]
    public function la_base_refuse_de_sortir_d_une_demande_annulee(): void
    {
        $demande = $this->demande(RequestStatus::Pending);
        $this->actingAs($this->citoyen)->post(route('citizen.requests.cancel', $demande));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/terminal/');

        DB::table('reissuance_requests')->where('id', $demande->id)
            ->update(['status' => 'under_review']);
    }

    /**
     * Le service et la table allowed_transitions doivent rester d'accord.
     *
     * Elles decrivent la meme machine a deux endroits : une divergence, c'est
     * un refus cote base que le code croyait autorise, ou l'inverse.
     */
    #[Test]
    public function les_deux_nouvelles_transitions_figurent_bien_en_base(): void
    {
        foreach ([['draft', 'T13'], ['pending', 'T14']] as [$depuis, $etiquette]) {
            $this->assertDatabaseHas('allowed_transitions', [
                'from_status' => $depuis,
                'to_status' => 'cancelled',
                'actor_role' => 'citizen',
                'label' => $etiquette,
            ]);
        }
    }

    /**
     * L'ecran de suivi s'affiche pour CHAQUE statut, y compris les nouveaux.
     *
     * L'ordre de la frise vivait dans un tableau indexe par la valeur du
     * statut : ajouter `cancelled` a l'enumeration a casse cet ecran, sans
     * qu'aucun outil ne previenne. Ce test parcourt tous les cas de
     * l'enumeration, donc il couvrira aussi le prochain statut ajoute.
     */
    #[Test]
    public function chaque_statut_a_un_rang_sur_la_frise(): void
    {
        foreach (RequestStatus::cases() as $statut) {
            $rang = $statut->timelineRank();

            $this->assertGreaterThanOrEqual(0, $rang, "Le statut {$statut->value} n'a pas de rang.");
        }
    }

    #[Test]
    public function l_ecran_de_suivi_s_affiche_pour_une_demande_annulee(): void
    {
        $demande = $this->demande(RequestStatus::Pending);

        $this->actingAs($this->citoyen)->post(route('citizen.requests.cancel', $demande));

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $demande->refresh()))
            ->assertOk()
            ->assertSee('Annulée par le demandeur');
    }

    #[Test]
    public function l_ecran_de_suivi_propose_l_annulation_quand_elle_est_possible(): void
    {
        $demande = $this->demande(RequestStatus::Pending);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $demande))
            ->assertOk()
            ->assertSee('Annuler ma demande');
    }

    #[Test]
    public function l_ecran_de_suivi_ne_la_propose_plus_apres_prise_en_charge(): void
    {
        $demande = $this->demande(RequestStatus::Pending);
        $officier = User::factory()->officer($this->centre)->create();

        app(RequestTransitionService::class)
            ->transition($demande, RequestStatus::UnderReview, $officier);
        $demande->forceFill(['assigned_officer_id' => $officier->id])->save();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $demande->refresh()))
            ->assertOk()
            ->assertDontSee('Annuler ma demande');
    }
}
