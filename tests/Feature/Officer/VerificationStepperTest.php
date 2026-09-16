<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'indicateur de progression ne coche que ce qui a ete fait (D-074).
 *
 * LE DEFAUT QUE CECI GARDE. L'indicateur marquait « ✓ terminée » toute etape
 * situee avant l'etape courante. C'est juste dans l'assistant du citoyen, ou
 * l'on ne peut pas atteindre l'etape n+1 sans avoir complete la n. Ce ne
 * l'etait pas ici : les cinq etapes de la verification se parcourent
 * librement.
 *
 * Un officier arrive a l'etape 5 lisait donc « ✓ ✓ ✓ ✓ » — et, dans la meme
 * page, un recapitulatif annoncant « Non renseignée » pour ces quatre etapes,
 * sous un bandeau rouge les enumerant comme manquantes. Un lecteur d'ecran
 * entendait « Vérification de la pièce d'identité — terminée ».
 *
 * Sur l'ecran ou se decide la delivrance d'un acte d'etat civil, annoncer
 * comme accompli un controle qui n'a pas eu lieu n'est pas un defaut
 * d'affichage.
 */
class VerificationStepperTest extends TestCase
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
            'national_id_number' => 'DEMO-200000042', 'completed_at' => now(),
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

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($this->demande->refresh(), RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
    }

    private function ecran(int $etape): TestResponse
    {
        return $this->actingAs($this->officier)->get(route('officer.verification.step', [
            'reissuanceRequest' => $this->demande, 'step' => $etape,
        ]));
    }

    /** @return list<string> le libellé de chaque étape marquée « terminée » */
    private function etapesCochees(TestResponse $reponse): array
    {
        preg_match_all(
            '#stepper__item--done.*?stepper__label">\s*(.+?)\s*<span#su',
            (string) $reponse->getContent(),
            $trouvees,
        );

        return array_map(fn (string $l): string => html_entity_decode(trim($l), ENT_QUOTES), $trouvees[1]);
    }

    /**
     * LE COEUR DU DEFAUT : aller jusqu'a la derniere etape sans rien
     * enregistrer ne coche rien.
     */
    #[Test]
    public function parcourir_les_etapes_sans_rien_enregistrer_n_en_coche_aucune(): void
    {
        $reponse = $this->ecran(5)->assertOk();

        $this->assertSame(
            [],
            $this->etapesCochees($reponse),
            "Aucun contrôle n'a été enregistré : aucune étape ne peut être « terminée »."
        );

        // La page se contredisait elle-meme : elle disait les deux a la fois.
        $reponse->assertSee('Not recorded');
    }

    /** Une etape enregistree, et une seule, est cochee. */
    #[Test]
    public function seule_une_etape_reellement_enregistree_est_cochee(): void
    {
        app(VerificationWorkflow::class)->record(
            $this->demande, 2, $this->officier, VerificationResult::Match
        );

        $this->assertSame(
            ['Identity document check'],
            $this->etapesCochees($this->ecran(5)->assertOk()),
        );
    }

    /**
     * Et l'assistant du citoyen, lui, garde l'ordre pour preuve : il REFUSE
     * l'etape n+1 tant que la n n'est pas complete, donc la position y vaut
     * bien ce qu'elle annonce.
     */
    #[Test]
    public function l_assistant_du_citoyen_conserve_la_progression_par_position(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Autre', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-200000043', 'completed_at' => now(),
        ]);

        $brouillon = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'reason' => 'lost',
        ]);
        $brouillon->forceFill(['last_completed_step' => 1])->save();

        $this->assertSame(
            ['Your details'],
            $this->etapesCochees(
                $this->actingAs($citoyen)->get(route('citizen.requests.step', [
                    'reissuanceRequest' => $brouillon, 'step' => 2,
                ]))->assertOk()
            ),
        );
    }
}
