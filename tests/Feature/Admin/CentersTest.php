<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\Commune;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le raccordement des centres d'etat civil (D-085).
 *
 * CE QUE CES TESTS SURVEILLENT. Trois choses, dans cet ordre d'importance :
 *
 * 1. Que l'ecran ne devienne pas une porte derobee vers les dossiers.
 *    L'administrateur ne voit AUCUNE demande ; il voit des nombres. Un nom de
 *    naissance qui apparaitrait un jour dans cette page serait une fuite, et
 *    c'est le test qui doit tomber, pas un auditeur qui doit le remarquer.
 * 2. Que le code du registre reste fige des qu'un dossier est arrive. La regle
 *    est appliquee par le controleur, pas par un champ desactive : le test
 *    envoie donc la requete directement, comme le ferait quelqu'un qui
 *    reactive le champ dans son navigateur.
 * 3. Que « raccorde » veuille dire quelque chose. Debrancher un centre doit
 *    empecher une NOUVELLE demande d'y arriver — y compris envoyee a la main
 *    — et ne doit toucher a aucune demande en cours.
 */
class CentersTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function l_ecran_liste_les_centres_avec_leur_raccordement(): void
    {
        $raccorde = CivilStatusCenter::factory()->create([
            'name' => 'Centre TEMOIN RACCORDE', 'is_active' => true,
        ]);
        $debranche = CivilStatusCenter::factory()->create([
            'name' => 'Centre TEMOIN DEBRANCHE', 'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.index'))
            ->assertOk()
            ->assertSee($raccorde->name)
            ->assertSee($debranche->name)
            ->assertSee(__('admin.centers.connected'))
            ->assertSee(__('admin.centers.disconnected'));
    }

    /**
     * LE TEST LE PLUS IMPORTANT DE CE FICHIER.
     *
     * L'ecran affiche des nombres tires des demandes. Si un jour quelqu'un
     * ajoute « la derniere demande recue » pour rendre service, ce test tombe.
     */
    #[Test]
    public function l_ecran_ne_montre_aucune_donnee_d_identite(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $demande = $this->demandeEnCours($centre);

        $reponse = $this->actingAs($this->admin)->get(route('admin.centers.index'));

        $reponse->assertOk()
            ->assertDontSee('Personne TRES-RECONNAISSABLE')
            ->assertDontSee('Père TRES-RECONNAISSABLE')
            ->assertDontSee('Mère TRES-RECONNAISSABLE')
            ->assertDontSee('Ville TRES-RECONNAISSABLE')
            // Meme la reference d'un dossier n'a pas sa place ici : cet ecran
            // parle de centres, pas de demandes.
            ->assertDontSee($demande->reference);
    }

    #[Test]
    public function les_nombres_affiches_comptent_bien_les_dossiers_du_centre(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $this->demandeEnCours($centre);
        $this->demandeEnCours($centre);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.index'))
            ->assertOk()
            ->assertSee(__('admin.centers.in_flight'))
            ->assertSeeInOrder([__('admin.centers.in_flight'), '2']);
    }

    /**
     * Un brouillon n'est pas arrive au centre.
     *
     * Il n'existe que pour son auteur, qui peut encore en changer le centre.
     * Le compter ferait croire a un dossier a traiter qui n'a jamais ete
     * envoye.
     */
    #[Test]
    public function un_brouillon_n_est_pas_compte(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        ReissuanceRequest::factory()->create([
            'user_id' => User::factory()->citizen()->create()->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
        ]);

        $ligne = ReissuanceRequest::aggregatesForAdministration()
            ->where('civil_status_center_id', $centre->id)
            ->first();

        $this->assertNull($ligne, 'Un brouillon ne doit pas apparaitre dans les agrégats.');
    }

    #[Test]
    public function l_ecran_avertit_quand_la_commune_n_a_aucun_maire_actif(): void
    {
        CivilStatusCenter::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.index'))
            ->assertOk()
            ->assertSee(__('admin.centers.no_mayor'));
    }

    #[Test]
    public function l_avertissement_disparait_quand_un_maire_actif_existe(): void
    {
        $centre = CivilStatusCenter::factory()->create(['is_active' => true]);
        User::factory()->mayor($centre->commune)->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.index'))
            ->assertOk()
            ->assertDontSee(__('admin.centers.no_mayor'));
    }

    /**
     * Un maire suspendu ne signe rien.
     *
     * Le compter comme present ferait taire l'avertissement alors que le
     * probleme est exactement celui qu'il annonce.
     */
    #[Test]
    public function un_maire_suspendu_ne_fait_pas_taire_l_avertissement(): void
    {
        $centre = CivilStatusCenter::factory()->create(['is_active' => true]);
        User::factory()->mayor($centre->commune)->create(['status' => 'suspended']);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.index'))
            ->assertOk()
            ->assertSee(__('admin.centers.no_mayor'));
    }

    #[Test]
    public function l_administrateur_raccorde_un_centre(): void
    {
        $commune = Commune::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.centers.store'), [
                'name' => "Centre d'état civil de Test",
                'city' => 'Ville de test',
                'commune_id' => $commune->id,
                'code' => 'tst-1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.centers.index'));

        $centre = CivilStatusCenter::where('name', "Centre d'état civil de Test")->firstOrFail();

        // La casse est normalisee : « tst-1 » et « TST-1 » sont le meme code.
        $this->assertSame('TST-1', $centre->code);
        $this->assertTrue($centre->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'center.connected',
            'auditable_type' => 'civil_status_center',
            'auditable_id' => $centre->id,
            'actor_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function deux_centres_ne_peuvent_pas_porter_le_meme_code(): void
    {
        CivilStatusCenter::factory()->create(['code' => 'DOUBLON']);

        $this->actingAs($this->admin)
            ->post(route('admin.centers.store'), [
                'name' => 'Autre centre',
                'city' => 'Ville',
                'commune_id' => Commune::factory()->create()->id,
                // Meme code a la casse pres : l'unicite ne doit pas s'y laisser
                // prendre.
                'code' => 'doublon',
            ])
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function debrancher_un_centre_est_journalise(): void
    {
        $centre = CivilStatusCenter::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->patch(route('admin.centers.update', $centre), [
                'name' => $centre->name,
                'city' => $centre->city,
                'commune_id' => $centre->commune_id,
                'code' => $centre->code,
                // La case n'est pas envoyee : c'est ainsi qu'un navigateur
                // transmet une case decochee.
            ])
            ->assertRedirect(route('admin.centers.index'));

        $this->assertFalse($centre->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'center.disconnected',
            'auditable_id' => $centre->id,
        ]);
    }

    #[Test]
    public function le_code_se_corrige_tant_qu_aucun_dossier_n_est_arrive(): void
    {
        $centre = CivilStatusCenter::factory()->create(['code' => 'FAUTE']);

        $this->actingAs($this->admin)
            ->patch(route('admin.centers.update', $centre), [
                'name' => $centre->name,
                'city' => $centre->city,
                'commune_id' => $centre->commune_id,
                'code' => 'JUSTE',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.centers.index'));

        $this->assertSame('JUSTE', $centre->fresh()->code);
    }

    #[Test]
    public function l_ecran_d_edition_affiche_le_code_comme_champ_quand_il_est_libre(): void
    {
        $centre = CivilStatusCenter::factory()->create(['code' => 'LIBRE']);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.edit', $centre))
            ->assertOk()
            ->assertSee('name="code"', false)
            ->assertDontSee(__('admin.centers.code_frozen_hint', ['count' => 1]));
    }

    /**
     * Quand le code est fige, l'ecran dit POURQUOI.
     *
     * Un champ simplement absent ferait chercher une option cachee ; la phrase
     * donne la raison et le nombre de dossiers deja arrives.
     */
    #[Test]
    public function l_ecran_d_edition_explique_pourquoi_le_code_est_fige(): void
    {
        $centre = CivilStatusCenter::factory()->create(['code' => 'EXPLIQUE']);
        $this->demandeEnCours($centre);

        $this->actingAs($this->admin)
            ->get(route('admin.centers.edit', $centre))
            ->assertOk()
            ->assertDontSee('name="code"', false)
            ->assertSee(__('admin.centers.code_frozen_hint', ['count' => 1]));
    }

    /**
     * La regle est appliquee par le controleur, pas par le formulaire.
     *
     * Un champ desactive se reactive en trois clics. Ce test envoie donc la
     * requete directement, et exige un refus annonce — un code silencieusement
     * ignore ferait croire a une modification qui n'a pas eu lieu.
     */
    #[Test]
    public function le_code_est_fige_des_qu_un_dossier_est_arrive(): void
    {
        $centre = CivilStatusCenter::factory()->create(['code' => 'FIGE']);
        $this->demandeEnCours($centre);

        $this->actingAs($this->admin)
            ->patch(route('admin.centers.update', $centre), [
                'name' => $centre->name,
                'city' => $centre->city,
                'commune_id' => $centre->commune_id,
                'code' => 'AUTRE',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame('FIGE', $centre->fresh()->code);
    }

    /**
     * Le reste du centre reste modifiable quand le code est fige.
     *
     * Sans ce test, figer le code pourrait figer la ligne entiere, et un centre
     * dont le nom change deviendrait impossible a corriger.
     */
    #[Test]
    public function le_nom_reste_modifiable_quand_le_code_est_fige(): void
    {
        $centre = CivilStatusCenter::factory()->create(['code' => 'FIGE2']);
        $this->demandeEnCours($centre);

        $this->actingAs($this->admin)
            ->patch(route('admin.centers.update', $centre), [
                'name' => 'Nom corrigé',
                'city' => $centre->city,
                'commune_id' => $centre->commune_id,
                'code' => $centre->code,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.centers.index'));

        $this->assertSame('Nom corrigé', $centre->fresh()->name);
    }

    /** Aucune route de suppression n'existe, et ce n'est pas un oubli. */
    #[Test]
    public function aucune_route_ne_supprime_un_centre(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        // 405, et non 404 : l'URL existe pour GET et PATCH, mais aucune route
        // n'y repond en DELETE. C'est exactement ce qu'on veut constater.
        $this->actingAs($this->admin)
            ->delete("/administration/centres/{$centre->id}")
            ->assertStatus(405);

        $this->assertModelExists($centre);
    }

    #[Test]
    public function un_officier_n_atteint_pas_l_ecran_des_centres(): void
    {
        $officier = User::factory()->officer(CivilStatusCenter::factory()->create())->create();

        $this->actingAs($officier)->get(route('admin.centers.index'))->assertForbidden();
        $this->actingAs($officier)->get(route('admin.centers.create'))->assertForbidden();
    }

    #[Test]
    public function un_citoyen_n_atteint_pas_l_ecran_des_centres(): void
    {
        $this->actingAs(User::factory()->citizen()->create())
            ->get(route('admin.centers.index'))
            ->assertForbidden();
    }

    /**
     * CE QUI DONNE UN SENS AU DRAPEAU.
     *
     * La liste proposee au citoyen filtrait deja sur `is_active`, mais la
     * validation ne le faisait pas : un identifiant de centre debranche, garde
     * dans un onglet ouvert ou pose a la main, passait. Trouve en construisant
     * cet ecran.
     */
    #[Test]
    public function une_demande_ne_peut_pas_choisir_un_centre_debranche(): void
    {
        $debranche = CivilStatusCenter::factory()->create(['is_active' => false]);
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-400000009', 'completed_at' => now(),
        ]);

        $brouillon = ReissuanceRequest::factory()->create([
            'user_id' => $citoyen->id,
            'civil_status_center_id' => null,
            'commune_id' => null,
        ]);

        $this->actingAs($citoyen)
            ->post(route('citizen.requests.save', ['reissuanceRequest' => $brouillon, 'step' => 3]), [
                'civil_status_center_id' => $debranche->id,
            ])
            ->assertSessionHasErrors('civil_status_center_id');

        $this->assertNull($brouillon->fresh()->civil_status_center_id);
    }

    /** Une demande en cours n'est pas touchee par le debranchement. */
    #[Test]
    public function debrancher_ne_touche_pas_une_demande_en_cours(): void
    {
        $centre = CivilStatusCenter::factory()->create(['is_active' => true]);
        $demande = $this->demandeEnCours($centre);

        $this->actingAs($this->admin)->patch(route('admin.centers.update', $centre), [
            'name' => $centre->name,
            'city' => $centre->city,
            'commune_id' => $centre->commune_id,
            'code' => $centre->code,
        ]);

        $demande->refresh();

        $this->assertSame(RequestStatus::Pending, $demande->status);
        $this->assertSame($centre->id, $demande->civil_status_center_id);
    }

    /**
     * Une demande deja envoyee dans ce centre, avec des donnees d'identite
     * volontairement reconnaissables : c'est ce que le test de fuite cherche.
     */
    private function demandeEnCours(CivilStatusCenter $centre): ReissuanceRequest
    {
        $citoyen = User::factory()->citizen()->create();

        $demande = ReissuanceRequest::factory()->submitted()->create([
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'full_name_at_birth' => 'Personne TRES-RECONNAISSABLE',
            'place_of_birth' => 'Ville TRES-RECONNAISSABLE',
            'father_name' => 'Père TRES-RECONNAISSABLE',
            'mother_name' => 'Mère TRES-RECONNAISSABLE',
        ]);

        app(RequestTransitionService::class)->transition($demande, RequestStatus::Pending, $citoyen);

        return $demande->refresh();
    }
}
