<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Officer\DecisionController;
use App\Http\Controllers\Officer\ReportController;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les rapports d'activite de l'officier (D-084).
 */
class ReportsTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
    }

    /** Le graphique dessine bien une barre par decision enregistree. */
    #[Test]
    public function le_graphique_porte_une_barre_par_type_de_decision(): void
    {
        $this->decision(DecisionType::Accepted);
        $this->decision(DecisionType::Accepted);
        $this->decision(DecisionType::Rejected, 'Motif écrit à la main.');

        $html = (string) $this->actingAs($this->officier)
            ->get(route('officer.reports'))->assertOk()->getContent();

        preg_match_all('/<rect[^>]*class="chart__rect[^"]*"/', $html, $barres);

        $this->assertCount(2, $barres[0],
            'Le graphique ne dessine pas une barre par type de decision present.');

        // Les hauteurs sont des attributs de geometrie, jamais des styles.
        $this->assertStringNotContainsString('style="height', $html);
        $this->assertStringNotContainsString('style="width', $html);
    }

    /**
     * AUCUN ATTRIBUT style= SUR CET ECRAN.
     *
     * La politique declare `style-src 'self'` sans `unsafe-inline` : le
     * navigateur REFUSE les attributs style=. La premiere version du
     * graphique ecrivait `style="height: 25%"` sur chaque barre, rendait 200,
     * et affichait douze traits de 3 px. Un graphique faux sur une page
     * valide, que seule la console signalait.
     */
    #[Test]
    public function aucun_style_en_ligne_n_est_ecrit(): void
    {
        $this->decision(DecisionType::Accepted);

        $html = (string) $this->actingAs($this->officier)
            ->get(route('officer.reports'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html,
            'Un attribut style= est ecrit : la politique de securite le refusera silencieusement.');
    }

    /**
     * UN MOTIF ECRIT A LA MAIN N'EST JAMAIS AFFICHE.
     *
     * Il peut nommer une personne ou une piece. Seuls les motifs proposes par
     * l'interface sont comptes nommement ; le reste est regroupe.
     */
    #[Test]
    public function un_motif_saisi_a_la_main_n_apparait_pas(): void
    {
        $this->decision(DecisionType::Rejected, 'Le demandeur PERSONNE-TRES-RECONNAISSABLE a menti.');

        $html = (string) $this->actingAs($this->officier)
            ->get(route('officer.reports'))->assertOk()->getContent();

        $this->assertStringNotContainsString('PERSONNE-TRES-RECONNAISSABLE', $html);
        $this->assertStringContainsString(__('officer.reports.other_reason'), $html);
    }

    /** Un motif pre-rempli, lui, est nomme : il ne porte aucune donnee. */
    #[Test]
    public function un_motif_prerempli_est_nomme(): void
    {
        $motif = DecisionController::rejectionReasons()[0];

        $this->decision(DecisionType::Rejected, $motif);

        $this->actingAs($this->officier)
            ->get(route('officer.reports'))
            ->assertOk()
            ->assertSee($motif, false);
    }

    /** Un officier ne voit que SES decisions, pas celles d'un collegue. */
    #[Test]
    public function les_decisions_d_un_collegue_ne_sont_pas_comptees(): void
    {
        $collegue = User::factory()->officer($this->centre)->create();

        $this->decision(DecisionType::Accepted, null, $collegue);

        $vue = $this->actingAs($this->officier)->get(route('officer.reports'))->assertOk();

        $total = array_sum(array_map(
            fn (array $s): int => $s['total'],
            $vue->viewData('series'),
        ));

        $this->assertSame(0, $total, "Les decisions d'un collegue sont comptees dans « votre activite ».");
    }

    /** Une periode inventee dans l'URL retombe sur le defaut. */
    #[Test]
    public function une_periode_inventee_retombe_sur_le_defaut(): void
    {
        $this->actingAs($this->officier)
            ->get(route('officer.reports', ['semaines' => 999]))
            ->assertOk()
            ->assertViewHas('semaines', 8);
    }

    /**
     * L'ORDRE DES TROIS TEINTES NE SE CHANGE PAS.
     *
     * Il n'est pas logique, il est optique : dans l'ordre « accepte, rejete,
     * escalade », l'ambre et le rouge se touchent dans la barre empilee et
     * leur ecart tombe a 14,1 en vision normale, sous le plancher de 15.
     * Mesure au validateur de palette, pas estimee a l'oeil.
     */
    #[Test]
    public function l_ordre_des_teintes_empechant_deux_rouges_voisins_est_fige(): void
    {
        $this->assertSame(
            [DecisionType::Rejected, DecisionType::Accepted, DecisionType::Escalated],
            ReportController::typesAffiches(),
            "L'ambre et le rouge redeviendraient voisins : deux couleurs indiscernables."
        );
    }

    private function decision(DecisionType $type, ?string $motif = null, ?User $acteur = null): void
    {
        $citoyen = User::factory()->citizen()->create();

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
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

        $demande->forceFill(['submitted_at' => now()])->save();
        app(RequestTransitionService::class)->transition($demande->refresh(), RequestStatus::Pending, $citoyen);

        RequestDecision::create([
            'request_id' => $demande->id,
            'actor_id' => ($acteur ?? $this->officier)->id,
            'actor_role' => UserRole::Officer->value,
            'decision' => $type->value,
            'reason' => $motif,
            'from_status' => RequestStatus::UnderReview->value,
            'to_status' => RequestStatus::AwaitingSignature->value,
        ]);
    }
}
