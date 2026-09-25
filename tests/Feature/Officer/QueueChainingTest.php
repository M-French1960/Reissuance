<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que la maquette du poste de l'officier apportait (D-082).
 *
 * DEUX MANQUES, tous deux visibles seulement a l'ecran.
 *
 * Le premier : `DecisionController` cherchait la demande suivante a prendre en
 * charge, puis JETAIT le resultat. Il ne s'en servait que comme booleen pour
 * choisir un message. L'officier lisait « une autre demande attend » et devait
 * la retrouver lui-meme dans la file. Le 8.2 du brief demande pourtant
 * « l'avancement dans la file sans retour au tableau de bord ».
 *
 * Le second : la file ne disait pas depuis combien de temps un demandeur
 * attend. C'est la seule information qui permet de prioriser, et elle
 * manquait.
 */
class QueueChainingTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
    }

    /**
     * APRES UNE DECISION, LE LIEN VERS LA SUIVANTE EST DONNE.
     *
     * Pas une simple mention : un lien cliquable vers le dossier nomme.
     */
    #[Test]
    public function une_decision_propose_d_ouvrir_la_demande_suivante(): void
    {
        $traitee = $this->demandeEnvoyee();
        $this->prendreEnCharge($traitee);

        $suivante = $this->demandeEnvoyee();

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $traitee), [
                'decision' => 'escalated',
                'reason' => 'Motif de test, dossier de démonstration.',
            ])
            ->assertRedirect(route('officer.queue'))
            ->assertSessionHas('statusAction', route('officer.verification.step', [
                'reissuanceRequest' => $suivante,
                'step' => 1,
            ]))
            ->assertSessionHas('statusActionLabel', fn (string $libelle): bool => str_contains($libelle, $suivante->reference));
    }

    /** Et s'il n'y a rien derriere, on ne propose rien. */
    #[Test]
    public function sans_demande_suivante_aucun_lien_n_est_propose(): void
    {
        $traitee = $this->demandeEnvoyee();
        $this->prendreEnCharge($traitee);

        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $traitee), [
                'decision' => 'escalated',
                'reason' => 'Motif de test, dossier de démonstration.',
            ])
            ->assertRedirect(route('officer.queue'))
            ->assertSessionMissing('statusAction');
    }

    /**
     * LE FLASH SAIT PORTER UNE ACTION.
     *
     * `x-alert` l'affichait depuis toujours ; `x-flash` ne la lui passait pas.
     * Un message qui annonce quelque chose a faire sans donner le moyen de le
     * faire est une impasse (8.1).
     */
    #[Test]
    public function le_message_affiche_le_lien_qu_il_porte(): void
    {
        $suivante = $this->demandeEnvoyee();

        $html = (string) $this->actingAs($this->officier)
            ->withSession([
                'status' => 'Décision enregistrée.',
                'statusAction' => route('officer.verification.step', [$suivante, 1]),
                'statusActionLabel' => 'Ouvrir '.$suivante->reference,
            ])
            ->get(route('officer.queue'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alert__action', $html);
        $this->assertStringContainsString('Ouvrir '.$suivante->reference, $html);
    }

    /**
     * LA COLONNE « ATTENTE » SE TAIT SUR UN DOSSIER CLOS.
     *
     * Une demande signee, refusee ou annulee n'attend plus rien. Y afficher
     * une duree laisserait croire qu'elle est encore en cours.
     */
    #[Test]
    public function la_colonne_attente_ne_parle_que_des_dossiers_en_cours(): void
    {
        $enCours = $this->demandeEnvoyee();

        $close = $this->demandeEnvoyee();
        $this->prendreEnCharge($close);

        /*
         * Le refus passe par la DECISION, comme dans la vraie vie : la machine
         * a etats refuse `pending → rejected` en direct, et elle a raison. On
         * corrige le test, pas le controle.
         */
        $this->actingAs($this->officier)
            ->post(route('officer.decision.store', $close->refresh()), [
                'decision' => 'rejected',
                'reason' => 'Motif de test, dossier de démonstration.',
            ])
            ->assertRedirect();

        $close->refresh();

        $html = (string) $this->actingAs($this->officier)
            ->get(route('officer.queue'))
            ->assertOk()
            ->getContent();

        // La ligne encore en cours porte une duree, la ligne close n'en porte pas.
        $ligneEnCours = $this->ligneDe($html, $enCours->reference);
        $ligneClose = $this->ligneDe($html, $close->reference);

        $this->assertMatchesRegularExpression('/\d+\s*(second|minute|hour|day|week|month|year)/i', $ligneEnCours,
            "La demande en cours n'affiche pas depuis combien de temps elle attend.");

        $this->assertDoesNotMatchRegularExpression('/\d+\s*(second|minute|hour|day|week|month|year)/i', $ligneClose,
            'Une demande close affiche une durée d\'attente, alors qu\'elle n\'attend plus.');
    }

    private function ligneDe(string $html, string $reference): string
    {
        preg_match('/<tr>(?:(?!<\/tr>).)*'.preg_quote($reference, '/').'.*?<\/tr>/s', $html, $m);

        $this->assertNotEmpty($m, "Ligne introuvable pour {$reference}.");

        return $m[0];
    }

    private function demandeEnvoyee(): ReissuanceRequest
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

        // Deposee il y a un moment : sans cela la duree d'attente est nulle.
        $demande->forceFill(['submitted_at' => now()->subDays(3)])->save();

        app(RequestTransitionService::class)->transition($demande->refresh(), RequestStatus::Pending, $citoyen);

        return $demande->refresh();
    }

    private function prendreEnCharge(ReissuanceRequest $demande): void
    {
        $this->actingAs($this->officier)
            ->post(route('officer.verification.claim', $demande))
            ->assertRedirect();
    }
}
