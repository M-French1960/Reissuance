<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Support\Tracking\RequestTimeline;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le tableau de bord du demandeur (D-079).
 *
 * CE QUE CES TESTS FERMENT. La maquette fournie proposait deux compteurs,
 * « Demandes en cours » et « Demandes terminées ». Trois etats terminent un
 * parcours : `signed`, `rejected` et `cancelled`. Les compter ensemble aurait
 * annonce « 1 demande terminee » a quelqu'un dont la demande venait d'etre
 * REFUSEE, et « terminee » se lit comme « aboutie ».
 *
 * Elle promettait aussi une notification par SMS que l'application n'envoie
 * pas, et une declaration de perte que le formulaire ne demande pas.
 */
class DashboardTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $citoyen;

    private ?User $officier = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-600000001', 'completed_at' => now(),
        ]);
    }

    /**
     * UNE DEMANDE REFUSEE N'EST PAS UN ACTE DELIVRE.
     *
     * Le compteur ne compte que les actes reellement signes. C'est la raison
     * pour laquelle il ne s'appelle pas « demandes terminees ».
     */
    #[Test]
    public function un_refus_ne_compte_pas_comme_un_acte_delivre(): void
    {
        $refusee = $this->demandeEnvoyee();
        $this->transition($refusee, RequestStatus::UnderReview);
        $this->transition($refusee, RequestStatus::Rejected);

        $vue = $this->actingAs($this->citoyen)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, $vue->viewData('delivres'),
            'Une demande refusee est comptee comme un acte delivre.');
        $this->assertSame(0, $vue->viewData('enCours'),
            'Une demande refusee est comptee comme en cours.');
    }

    /** Un brouillon est « en cours » : il est commence et il attend son auteur. */
    #[Test]
    public function un_brouillon_compte_comme_une_demande_en_cours(): void
    {
        ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'reason' => 'lost',
        ]);

        $vue = $this->actingAs($this->citoyen)->get(route('dashboard'))->assertOk();

        $this->assertSame(1, $vue->viewData('enCours'));
        $this->assertNotNull($vue->viewData('brouillon'));
        // Un brouillon n'a pas de frise a montrer : il a un formulaire a finir.
        $this->assertNull($vue->viewData('suivie'));
    }

    /**
     * LA FRISE DU TABLEAU DE BORD EST CELLE DE LA PAGE DE SUIVI.
     *
     * Une deuxieme version, deduite du statut courant, aurait refait le defaut
     * de D-075 sans que personne le voie : la premiere serait restee juste.
     */
    #[Test]
    public function la_frise_est_la_meme_que_celle_de_la_page_de_suivi(): void
    {
        $demande = $this->demandeEnvoyee();
        $this->transition($demande, RequestStatus::UnderReview);

        $vue = $this->actingAs($this->citoyen)->get(route('dashboard'))->assertOk();

        $this->assertSame(
            app(RequestTimeline::class)->for($demande->refresh()),
            $vue->viewData('frise'),
        );
    }

    /** Un demandeur ne voit que ses propres paiements et ses propres demandes. */
    #[Test]
    public function le_tableau_de_bord_ne_montre_rien_d_un_autre_demandeur(): void
    {
        $autre = User::factory()->citizen()->create();
        $autre->profile()->create([
            'first_name' => 'Autre', 'last_name' => 'PERSONNE',
            'national_id_number' => 'DEMO-600000002', 'completed_at' => now(),
        ]);

        ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => 'PHX-AUTR-UIAA',
            'user_id' => $autre->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
        ]);

        $vue = $this->actingAs($this->citoyen)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, $vue->viewData('enCours'));
        $vue->assertDontSee('PHX-AUTR-UIAA');
    }

    /**
     * L'ECRAN NE PROMET NI SMS NI DECLARATION DE PERTE.
     *
     * Les canaux de notification sont la base et le courriel. Les pieces
     * acceptees sont `selfie` et `id_document`, et rien d'autre.
     */
    #[Test]
    public function le_tableau_de_bord_ne_promet_que_ce_que_l_application_fait(): void
    {
        foreach (['en', 'fr'] as $langue) {
            $html = strip_tags((string) $this->actingAs($this->citoyen)
                ->withSession(['locale' => $langue])
                ->get(route('dashboard'))
                ->assertOk()
                ->getContent());

            foreach (['SMS', 'déclaration de perte', 'loss declaration'] as $promesse) {
                $this->assertStringNotContainsStringIgnoringCase($promesse, $html,
                    "Le tableau de bord promet « {$promesse} » en {$langue}, ce que l'application ne fait pas.");
            }
        }
    }

    private function demandeEnvoyee(): ReissuanceRequest
    {
        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
        ]);

        $demande->forceFill(['submitted_at' => now()])->save();
        $this->transition($demande->refresh(), RequestStatus::Pending);

        return $demande->refresh();
    }

    /**
     * Chaque transition est faite PAR QUI A LE DROIT DE LA FAIRE.
     *
     * Le service refuse `pending → under_review` a un citoyen, et il a raison :
     * c'est un controle d'autorisation, pas une formalite de test. C'est donc
     * l'officier du centre qui l'opere ici, comme dans la vraie vie.
     */
    private function transition(ReissuanceRequest $demande, RequestStatus $vers): void
    {
        $acteur = match ($vers) {
            RequestStatus::Pending, RequestStatus::Cancelled => $this->citoyen,
            default => $this->officier ??= User::factory()->officer($this->centre)->create(),
        };

        app(RequestTransitionService::class)->transition($demande, $vers, $acteur);
    }
}
