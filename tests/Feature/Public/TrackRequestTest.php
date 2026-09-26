<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le suivi public d'une demande (D-096).
 *
 * J'AI REFUSE CE SUIVI DEUX FOIS. Ce qui l'a rendu defendable est un SECOND
 * FACTEUR, pas un changement d'avis. La moitie de ces tests verifie donc les
 * barrieres, pas la fonction : ce sont elles qui font la difference entre cet
 * ecran et celui que j'ai refuse.
 */
class TrackRequestTest extends TestCase
{
    private User $citoyen;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($centre)->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'phone' => '+237 6 99 00 12 34', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
        ]);
    }

    private function envoyer(): void
    {
        $this->demande->forceFill(['submitted_at' => now()])->save();
        app(RequestTransitionService::class)->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $this->demande->refresh();
    }

    private function suivre(?string $reference = null, string $chiffres = '1234'): TestResponse
    {
        return $this->post(route('track.check'), [
            'reference' => $reference ?? $this->demande->reference,
            'telephone' => $chiffres,
        ]);
    }

    #[Test]
    public function la_page_est_accessible_sans_compte(): void
    {
        $this->get(route('track.show'))->assertOk()->assertSee('Track a request');
    }

    #[Test]
    public function les_deux_facteurs_ensemble_donnent_l_avancement(): void
    {
        $this->envoyer();

        $this->suivre()
            ->assertOk()
            ->assertSee($this->demande->reference)
            ->assertSee('Done');
    }

    /**
     * LE COEUR DU REFUS PRECEDENT : la reference seule n'ouvre rien.
     */
    #[Test]
    public function la_reference_seule_n_ouvre_rien(): void
    {
        $this->envoyer();

        $this->suivre(chiffres: '0000')
            ->assertOk()
            ->assertSee('No request matches')
            ->assertDontSee('Done');
    }

    /**
     * SANS NUMERO AU DOSSIER, PAS DE SUIVI. La tentation serait de « tolerer »
     * la reference seule dans ce cas ; cette tolerance serait exactement la
     * porte que j'ai refusee deux fois.
     */
    #[Test]
    public function un_dossier_sans_telephone_n_est_pas_suivable(): void
    {
        $this->envoyer();
        $this->citoyen->profile->forceFill(['phone' => null])->save();

        foreach (['1234', '0000', ''] as $essai) {
            $this->suivre(chiffres: $essai)
                ->assertOk()
                ->assertSee('No request matches');
        }
    }

    /**
     * L'ORACLE A REFERENCES. Si la reponse distinguait « reference inconnue »
     * de « telephone faux », un attaquant balaierait les references d'abord,
     * puis dix mille combinaisons sur celles qui « existent presque ».
     */
    #[Test]
    public function la_reponse_est_la_meme_pour_tous_les_echecs(): void
    {
        $this->envoyer();

        $reponses = [];

        foreach ([
            ['PHX-ZZZZ-ZZZZ', '1234'],   // reference inconnue
            ['pas-une-reference', '1234'], // reference mal formee
            [$this->demande->reference, '0000'], // telephone faux
            [$this->demande->reference, 'ab'],   // second facteur mal forme
        ] as [$reference, $chiffres]) {
            $reponses[] = $this->suivre($reference, $chiffres)
                ->assertOk()
                ->assertSee('No request matches')
                // Le formulaire REAFFICHE la reference saisie, et c'est
                // voulu : on vise donc le titre du resultat, pas la simple
                // presence du texte dans la page.
                ->assertDontSee('Request '.$this->demande->reference)
                ->getStatusCode();
        }

        $this->assertSame([200, 200, 200, 200], $reponses);
    }

    /** Un brouillon n'a pas ete envoye : rien ne le concerne au dehors. */
    #[Test]
    public function un_brouillon_n_est_pas_suivable(): void
    {
        $this->assertSame(RequestStatus::Draft, $this->demande->status);

        $this->suivre()->assertOk()->assertSee('No request matches');
    }

    /**
     * CE QUE LA PAGE NE DIT PAS. Le visiteur public n'est pas forcement le
     * demandeur : il obtient l'etape atteinte, jamais l'identite.
     */
    #[Test]
    public function la_reponse_ne_porte_aucune_donnee_personnelle(): void
    {
        $this->envoyer();
        $centre = $this->demande->center;

        $reponse = $this->suivre()->assertOk();

        $reponse->assertDontSee('Personne');
        $reponse->assertDontSee('DE TEST');
        $reponse->assertDontSee('699001234');
        $reponse->assertDontSee($this->citoyen->email);
        // Ni le centre, que la frise du demandeur affiche pourtant.
        $reponse->assertDontSee($centre->name);
    }

    #[Test]
    public function le_motif_d_un_rejet_n_est_pas_donne(): void
    {
        $this->envoyer();
        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $transitions->transition(
            $this->demande,
            RequestStatus::Rejected,
            $this->officier,
            'Piece illisible et non conforme au registre',
        );

        $this->suivre()
            ->assertOk()
            ->assertSee('was rejected')
            ->assertDontSee('illisible');
    }

    /** Une consultation qui aboutit laisse une trace ; un echec, jamais. */
    #[Test]
    public function seules_les_consultations_abouties_sont_journalisees(): void
    {
        $this->envoyer();

        $this->suivre(chiffres: '0000');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'request.tracked_publicly']);

        $this->suivre();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'request.tracked_publicly',
            'auditable_id' => $this->demande->id,
            'actor_id' => null,
        ]);
    }

    /**
     * La limitation de debit est plus serree qu'a la verification : un second
     * facteur de quatre chiffres n'a que dix mille valeurs.
     */
    #[Test]
    public function la_route_est_limitee_en_debit(): void
    {
        $this->envoyer();

        for ($i = 0; $i < 5; $i++) {
            $this->suivre(chiffres: '0000')->assertOk();
        }

        $this->suivre(chiffres: '0000')->assertStatus(429);
    }
}
