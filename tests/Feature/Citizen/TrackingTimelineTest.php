<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Enums\RequestStatus;
use App\Http\Controllers\Citizen\RequestTrackingController;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La frise « Où en est ma demande » ne doit annoncer que ce qui a eu lieu.
 *
 * LE DEFAUT QUE CECI GARDE. `signed`, `rejected` et `cancelled` partagent le
 * rang 4 de la frise — le parcours s'arrete la, qu'il aboutisse ou non. Ce
 * rang servait a decider quels jalons etaient « terminés », si bien qu'un
 * brouillon annule affichait « Demande envoyée ✓ terminé », « Vérification par
 * l'officier ✓ terminé » et « Décision du maire ✓ terminé » : trois etapes qui
 * n'avaient jamais eu lieu.
 *
 * Annoncer a un demandeur que son dossier a ete instruit alors qu'il ne l'a
 * pas ete n'est pas un defaut d'affichage : c'est une information fausse sur
 * un acte d'etat civil.
 */
class TrackingTimelineTest extends TestCase
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
            'national_id_number' => 'DEMO-500000001', 'completed_at' => now(),
        ]);
    }

    private function demande(): ReissuanceRequest
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

        return $demande->refresh();
    }

    /** @return list<string> les états de la frise, dans l'ordre */
    private function frise(ReissuanceRequest $demande): array
    {
        $controleur = app(RequestTrackingController::class);
        $methode = new \ReflectionMethod($controleur, 'timeline');
        $methode->setAccessible(true);

        return array_column($methode->invoke($controleur, $demande->refresh()), 'etat');
    }

    /** Un brouillon annulé n'a jamais été instruit : rien n'est « terminé ». */
    #[Test]
    public function un_brouillon_annule_n_annonce_aucune_etape_terminee(): void
    {
        $demande = $this->demande();

        app(RequestTransitionService::class)->transition(
            $demande, RequestStatus::Cancelled, $this->citoyen
        );

        $this->assertSame(
            ['arrete', 'arrete', 'arrete', 'arrete'],
            $this->frise($demande),
            "Un brouillon annulé n'a franchi aucun jalon."
        );
    }

    /** Une demande envoyée puis annulée : le premier jalon a bien eu lieu. */
    #[Test]
    public function une_demande_envoyee_puis_annulee_conserve_le_jalon_franchi(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::Cancelled, $this->citoyen);

        $this->assertSame(
            ['fait', 'arrete', 'arrete', 'arrete'],
            $this->frise($demande),
            "L'envoi a eu lieu ; la vérification, la décision et l'acte non."
        );
    }

    /** Refus par l'officier : la décision du maire n'a jamais eu lieu. */
    #[Test]
    public function un_refus_de_l_officier_n_annonce_pas_une_decision_du_maire(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $service->transition($demande->refresh(), RequestStatus::Rejected, $officier);

        $this->assertSame(
            ['fait', 'fait', 'arrete', 'arrete'],
            $this->frise($demande),
            'Le maire ne s’est jamais prononcé sur ce dossier.'
        );
    }

    /** Un parcours abouti reste entièrement « terminé ». */
    #[Test]
    public function un_parcours_abouti_reste_entierement_termine(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();
        $maire = User::factory()->mayor($this->centre->commune)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $service->transition($demande->refresh(), RequestStatus::AwaitingSignature, $officier);
        $service->transition($demande->refresh(), RequestStatus::Signed, $maire);

        $this->assertSame(['fait', 'fait', 'fait', 'fait'], $this->frise($demande));
    }

    /** Un dossier escaladé puis signé : le jalon sauté reste « terminé ». */
    #[Test]
    public function un_dossier_escalade_puis_signe_ne_perd_pas_de_jalon(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();
        $maire = User::factory()->mayor($this->centre->commune)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $service->transition($demande->refresh(), RequestStatus::Escalated, $officier);
        $service->transition($demande->refresh(), RequestStatus::Signed, $maire);

        $this->assertSame(
            ['fait', 'fait', 'fait', 'fait'],
            $this->frise($demande),
            "La signature a eu lieu : le jalon « Décision du maire » n'est pas sauté."
        );
    }

    /** Un dossier en cours n'annonce pas les étapes à venir comme faites. */
    #[Test]
    public function un_dossier_en_cours_n_anticipe_rien(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);

        $this->assertSame(['fait', 'fait', 'a_venir', 'a_venir'], $this->frise($demande));
    }
}
