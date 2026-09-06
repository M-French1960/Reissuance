<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'assistant de demande.
 *
 * Le prototype laissait atteindre le paiement sans nom, sans e-mail et sans
 * photo, en trois clics (docs/AUDIT_FRONTEND.md 5.3). Ces tests verifient
 * que ce n'est plus possible.
 */
class RequestWizardTest extends TestCase
{
    private User $citoyen;

    private CivilStatusCenter $centre;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne',
            'last_name' => 'DE TEST',
            'birth_date' => '1990-01-15',
            'birth_place' => 'Ville de test',
            'phone' => '+237600000000',
            'address' => 'Adresse de test',
            'completed_at' => now(),
        ]);
    }

    private function brouillon(): ReissuanceRequest
    {
        $this->actingAs($this->citoyen)->post(route('citizen.requests.start'))->assertRedirect();

        return ReissuanceRequest::withoutGlobalScopes()
            ->where('user_id', $this->citoyen->id)->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function donneesEtape2(): array
    {
        return [
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST',
            'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST',
            'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ];
    }

    private function ajouteLesPieces(ReissuanceRequest $draft): void
    {
        foreach (['selfie', 'id_document'] as $kind) {
            $this->actingAs($this->citoyen)->post(
                route('citizen.requests.attachments.store', $draft),
                ['kind' => $kind, 'file' => UploadedFile::fake()->image("{$kind}.jpg", 800, 600)]
            )->assertSessionHasNoErrors();
        }
    }

    #[Test]
    public function un_profil_incomplet_renvoie_vers_le_profil(): void
    {
        $sansProfil = User::factory()->citizen()->create();

        $this->actingAs($sansProfil)->post(route('citizen.requests.start'))
            ->assertRedirect(route('citizen.profile.edit'));

        $this->assertSame(0, ReissuanceRequest::withoutGlobalScopes()->where('user_id', $sansProfil->id)->count());
    }

    #[Test]
    public function le_parcours_complet_aboutit_a_une_demande_envoyee(): void
    {
        $draft = $this->brouillon();
        $this->assertSame(RequestStatus::Draft, $draft->status);

        $this->actingAs($this->citoyen);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]),
            ['reason' => 'lost', 'copies_requested' => 2])->assertSessionHasNoErrors();
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]),
            $this->donneesEtape2())->assertSessionHasNoErrors();
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 3]),
            ['civil_status_center_id' => $this->centre->id])->assertSessionHasNoErrors();

        $this->ajouteLesPieces($draft);

        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]))
            ->assertRedirect(route('citizen.requests.show', $draft));

        $draft->refresh();
        $this->assertSame(RequestStatus::Pending, $draft->status);
        $this->assertNotNull($draft->submitted_at);
        $this->assertNotNull($draft->consent_given_at);
        // La commune est deduite du centre, jamais prise dans la requete.
        $this->assertSame($this->centre->commune_id, $draft->commune_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $draft->id,
            'from_status' => 'draft',
            'to_status' => 'pending',
        ]);
    }

    /** Le defaut central du prototype : on ne saute plus aucune etape. */
    #[Test]
    public function on_ne_peut_pas_sauter_une_etape(): void
    {
        $draft = $this->brouillon();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 4]))
            ->assertRedirect(route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 1]));
    }

    #[Test]
    public function une_demande_ne_peut_pas_etre_envoyee_sans_les_deux_pieces(): void
    {
        $draft = $this->brouillon();

        $this->actingAs($this->citoyen);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]), ['reason' => 'lost', 'copies_requested' => 1]);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]), $this->donneesEtape2());
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 3]), ['civil_status_center_id' => $this->centre->id]);

        // Aucune piece.
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]))
            ->assertSessionHasErrors('attachments');
        $this->assertSame(RequestStatus::Draft, $draft->refresh()->status);

        // Une seule piece : toujours refuse.
        $this->post(route('citizen.requests.attachments.store', $draft),
            ['kind' => 'selfie', 'file' => UploadedFile::fake()->image('s.jpg')]);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]))
            ->assertSessionHasErrors('attachments');
        $this->assertSame(RequestStatus::Draft, $draft->refresh()->status);
    }

    #[Test]
    public function chaque_etape_est_validee_cote_serveur(): void
    {
        $draft = $this->brouillon();
        $this->actingAs($this->citoyen);

        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]), [])
            ->assertSessionHasErrors(['reason', 'copies_requested']);

        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]),
            ['reason' => 'lost', 'copies_requested' => 1]);

        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]), [])
            ->assertSessionHasErrors(['full_name_at_birth', 'date_of_birth', 'registration_year', 'father_name']);

        // Annee d'enregistrement dans le futur : refusee.
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]),
            array_merge($this->donneesEtape2(), ['registration_year' => (int) date('Y') + 1]))
            ->assertSessionHasErrors('registration_year');

        $this->assertSame(1, $draft->refresh()->last_completed_step);
    }

    /** Le brouillon survit a une interruption : c'est tout l'interet de D-010. */
    #[Test]
    public function un_brouillon_interrompu_reprend_ou_il_s_est_arrete(): void
    {
        $draft = $this->brouillon();
        $this->actingAs($this->citoyen);

        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]), ['reason' => 'damaged', 'copies_requested' => 3]);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]), $this->donneesEtape2());

        $this->assertSame(2, $draft->refresh()->last_completed_step);
        $this->assertSame('damaged', $draft->reason);
        $this->assertSame(3, $draft->copies_requested);

        // Nouvelle session : on doit retomber sur l'etape 3.
        $this->post(route('citizen.requests.start'))
            ->assertRedirect(route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3]));

        // Et aucun second brouillon n'a ete cree.
        $this->assertSame(1, ReissuanceRequest::withoutGlobalScopes()->where('user_id', $this->citoyen->id)->count());
    }

    /**
     * Le refus est un 404, pas un 403, et c'est volontairement mieux.
     *
     * La portee globale s'applique des la liaison de modele : le brouillon
     * d'un autre citoyen est introuvable, donc la Policy n'est meme pas
     * atteinte. La reponse ne confirme pas l'existence de la ressource, ce
     * qu'un 403 ferait.
     */
    #[Test]
    public function un_citoyen_ne_peut_pas_modifier_le_brouillon_d_un_autre(): void
    {
        $draft = $this->brouillon();
        $reasonAvant = $draft->reason;
        $intrus = User::factory()->citizen()->create();

        $this->actingAs($intrus)
            ->get(route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 1]))
            ->assertNotFound();

        $this->actingAs($intrus)
            ->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]),
                ['reason' => 'damaged', 'copies_requested' => 9])
            ->assertNotFound();

        // Et rien n'a bouge.
        $draft->refresh();
        $this->assertSame($reasonAvant, $draft->reason);
        $this->assertSame(0, $draft->last_completed_step);
    }

    /** Une demande envoyee n'est plus modifiable : seule une nouvelle demande. */
    #[Test]
    public function une_demande_envoyee_n_est_plus_modifiable(): void
    {
        $draft = $this->brouillon();
        $this->actingAs($this->citoyen);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]), ['reason' => 'lost', 'copies_requested' => 1]);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]), $this->donneesEtape2());
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 3]), ['civil_status_center_id' => $this->centre->id]);
        $this->ajouteLesPieces($draft);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]));

        $this->assertSame(RequestStatus::Pending, $draft->refresh()->status);

        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]), $this->donneesEtape2())
            ->assertForbidden();
        $this->post(route('citizen.requests.attachments.store', $draft),
            ['kind' => 'selfie', 'file' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();
    }

    #[Test]
    public function seul_un_citoyen_accede_a_l_assistant(): void
    {
        $officier = User::factory()->officer($this->centre)->create();

        $this->actingAs($officier)->post(route('citizen.requests.start'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('citizen.requests.index'))->assertForbidden();
    }

    #[Test]
    public function la_frise_de_suivi_reflete_l_avancement_reel(): void
    {
        $draft = $this->brouillon();
        $this->actingAs($this->citoyen);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]), ['reason' => 'lost', 'copies_requested' => 1]);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]), $this->donneesEtape2());
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 3]), ['civil_status_center_id' => $this->centre->id]);
        $this->ajouteLesPieces($draft);
        $this->post(route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]));

        $reponse = $this->get(route('citizen.requests.show', $draft))->assertOk();

        $reponse->assertSee('Demande envoyée');
        $reponse->assertSee('Envoyée — en attente de traitement');
        // L'etat n'est jamais porte par la seule couleur.
        $reponse->assertSee('— terminé');
        // Aucun delai chiffre tant que la question D8 est ouverte.
        $reponse->assertDontSee('jours ouvrés');
    }
}
