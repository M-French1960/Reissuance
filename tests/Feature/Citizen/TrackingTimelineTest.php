<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Enums\DecisionType;
use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\RequestDecision;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Support\Tracking\RequestTimeline;
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
        // Plus de reflexion : la frise est un service depuis D-079, parce que
        // le tableau de bord en a besoin lui aussi et qu'il ne doit pas en
        // exister une deuxieme version.
        return array_column(app(RequestTimeline::class)->for($demande->refresh()), 'etat');
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
    /**
     * ENTRER DANS UNE ETAPE N'EST PAS L'AVOIR TERMINEE (D-087).
     *
     * CE TEST ENCODAIT LE DEFAUT. Il attendait `['fait', 'fait', ...]` sur un
     * dossier ENCORE en cours d'examen : il verifiait que la frise n'anticipe
     * pas les etapes futures — ce qu'elle ne faisait pas — et acceptait au
     * passage qu'elle annonce « Vérification par l'officier : terminé » alors
     * que l'agent avait le dossier sous les yeux.
     *
     * Le vert de la suite prouvait donc ce que le test regardait, pas que
     * l'ecran disait vrai. Trouve en regardant la page, pas en lisant le code.
     */
    #[Test]
    public function une_verification_commencee_n_est_pas_une_verification_terminee(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);

        $this->assertSame(['fait', 'en_cours', 'a_venir', 'a_venir'], $this->frise($demande));
    }

    /** Le dossier qui attend le maire n'a pas encore SA decision. */
    #[Test]
    public function un_dossier_qui_attend_le_maire_n_annonce_pas_sa_decision(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $service->transition($demande->refresh(), RequestStatus::AwaitingSignature, $officier);

        // La verification, elle, est bel et bien terminee : le dossier l'a
        // depassee.
        $this->assertSame(['fait', 'fait', 'en_cours', 'a_venir'], $this->frise($demande));
    }

    /**
     * LE NOM DU CENTRE NE BEGAIE PAS (D-073).
     *
     * Les centres s'appellent « Centre d'état civil de Yaoundé I ». Prefixer
     * le libelle donnait « Transmise au centre d'état civil de Centre d'état
     * civil de Yaoundé I ». Trouve en regardant l'ecran, pas en le testant.
     */
    #[Test]
    public function le_nom_du_centre_n_est_pas_repete(): void
    {
        $this->centre->forceFill(['name' => "Centre d'état civil de Yaoundé I"])->save();

        $contenu = (string) $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $this->demande()))
            ->getContent();

        $this->assertStringNotContainsString(
            "centre d'état civil de Centre d'état civil",
            $contenu,
            'Le libellé répète le mot « centre d’état civil ».'
        );
    }

    /**
     * ET LE TEMPS SUIT L'ETAT.
     *
     * « Vous pourrez télécharger votre acte » s'affichait sous une etape
     * marquee « terminé » : le futur sous un fait accompli.
     */
    #[Test]
    public function le_dernier_jalon_parle_au_present_quand_l_acte_existe(): void
    {
        $demande = $this->demande();

        $detail = $this->detailDuDernierJalon($demande);
        $this->assertStringContainsString('You will be able to', $detail);

        // Une fois l'acte signé, le même jalon parle au présent.
        DocumentSignature::create([
            'request_id' => $demande->id,
            'mayor_id' => User::factory()->mayor($this->centre->commune)->create()->id,
            'document_hash' => str_repeat('a', 64),
            'provider' => 'fake-signature',
            'legally_binding' => false,
            'signed_at' => now(),
        ]);

        $detail = $this->detailDuDernierJalon($demande->refresh());
        $this->assertStringContainsString('Your certificate is ready', $detail);
    }

    private function detailDuDernierJalon(ReissuanceRequest $demande): string
    {
        $jalons = app(RequestTimeline::class)->for($demande->refresh());

        return (string) end($jalons)['detail'];
    }

    /**
     * ET L'EN-TETE NE BEGAIE PAS DAVANTAGE (D-075).
     *
     * La correction precedente n'avait touche que la frise. L'en-tete du meme
     * ecran affichait « Centre d'état civil : Centre d'état civil de Yaoundé I
     * — commune de Yaoundé I » : deux repetitions sur une seule ligne. Trouve
     * en REGARDANT l'ecran deja corrige, pas en le testant.
     */
    #[Test]
    public function l_en_tete_ne_repete_ni_le_libelle_ni_la_commune(): void
    {
        $this->centre->commune->forceFill(['name' => 'Yaoundé I'])->save();
        $this->centre->forceFill(['name' => "Centre d'état civil de Yaoundé I"])->save();

        $contenu = (string) $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $this->demande()))
            ->getContent();

        $this->assertStringNotContainsString("Centre d'état civil : Centre", $contenu);
        $this->assertStringNotContainsString('commune de Yaoundé I', $contenu);
        $this->assertStringContainsString('Centre d&#039;état civil de Yaoundé I', $contenu);
    }

    /**
     * MAIS LA COMMUNE RESTE DITE QUAND LE NOM NE LA PORTE PAS : une commune
     * peut avoir plusieurs centres, et « Centre annexe de Tsinga » ne dit pas
     * ou il se trouve.
     */
    #[Test]
    public function la_commune_est_precisee_quand_le_nom_du_centre_ne_la_porte_pas(): void
    {
        $this->centre->commune->forceFill(['name' => 'Yaoundé II'])->save();
        $this->centre->forceFill(['name' => 'Centre annexe de Tsinga'])->save();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $this->demande()))
            ->assertSee('Centre annexe de Tsinga — commune de Yaoundé II');
    }

    /**
     * LE MOTIF DU REFUS EST DIT AU DEMANDEUR (D-075).
     *
     * Il ne l'etait nulle part. Le motif est OBLIGATOIRE pour refuser — le
     * controleur l'exige, une contrainte l'impose en base — et l'agent qui le
     * saisit lit « Il sera visible dans le dossier ». L'ecran de suivi
     * affichait « Refusée » et rien d'autre, tandis que la page des
     * notifications renvoyait ici en promettant « le détail d'une demande,
     * motif d'un refus compris ».
     *
     * Refuser a quelqu'un un acte d'etat civil sans lui en donner la raison
     * n'est pas un defaut d'affichage.
     */
    #[Test]
    public function un_refus_dit_son_motif_au_demandeur(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $service->transition($demande->refresh(), RequestStatus::Rejected, $officier);

        RequestDecision::create([
            'request_id' => $demande->id,
            'actor_id' => $officier->id,
            'actor_role' => $officier->role->value,
            'decision' => DecisionType::Rejected->value,
            'reason' => 'Les photographies ne correspondent pas à la pièce fournie.',
            'internal_notes' => 'NOTE INTERNE QUI NE DOIT PAS SORTIR',
            'from_status' => RequestStatus::UnderReview->value,
            'to_status' => RequestStatus::Rejected->value,
        ]);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $demande))
            ->assertOk()
            ->assertSee('Why this request was rejected')
            ->assertSee('Les photographies ne correspondent pas')
            // Et ce qui reste entre agents y reste.
            ->assertDontSee('NOTE INTERNE QUI NE DOIT PAS SORTIR');
    }

    /**
     * ET UN JALON NON ATTEINT NE PROMET PLUS RIEN.
     *
     * « Acte disponible — non atteint » s'affichait au-dessus de « Vous
     * pourrez télécharger votre acte », sur un dossier refuse.
     */
    #[Test]
    public function un_jalon_non_atteint_n_annonce_aucun_avenir(): void
    {
        $demande = $this->demande();
        $service = app(RequestTransitionService::class);
        $officier = User::factory()->officer($this->centre)->create();

        $service->transition($demande, RequestStatus::Pending, $this->citoyen);
        $service->transition($demande->refresh(), RequestStatus::UnderReview, $officier);
        $service->transition($demande->refresh(), RequestStatus::Rejected, $officier);

        $this->actingAs($this->citoyen)
            ->get(route('citizen.requests.show', $demande))
            ->assertOk()
            ->assertSee('not reached')
            ->assertDontSee('You will be able to download your certificate');
    }
}
