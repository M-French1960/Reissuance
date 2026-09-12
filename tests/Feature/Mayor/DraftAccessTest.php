<?php

declare(strict_types=1);

namespace Tests\Feature\Mayor;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\ActDraft;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\DocumentBuilder;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsPdfText;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * Le maire lit le projet qu'il signe — et lui seul, avec l'officier.
 *
 * CE QUE CES TESTS FERMENT (D-068). D-064 a scelle le lien entre le projet
 * redige par l'officier et l'acte signe par le maire, au moyen d'une empreinte
 * de contenu. Mais aucune route ne servait le projet : le maire signait un
 * document qu'il n'avait jamais ouvert. L'empreinte prouvait que le contenu
 * n'avait pas bouge ; elle ne prouvait pas qu'il avait ete lu.
 *
 * LE REFUS QUI COMPTE LE PLUS EST CELUI DU CITOYEN. Un projet n'est pas son
 * acte : le lui montrer reviendrait a lui remettre un document qui ressemble a
 * son acte avant meme que le maire n'ait decide.
 */
class DraftAccessTest extends TestCase
{
    use ReadsPdfText;
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $officier;

    private User $maire;

    private ReissuanceRequest $demande;

    private ActDraft $projet;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();

        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-810000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Ville de test',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande->refresh(), RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande->refresh(), RequestStatus::UnderReview, $this->officier);

        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 2, 3, 4, 5] as $n) {
            $workflow->record($this->demande, $n, $this->officier, VerificationResult::Match);
        }

        $transitions->transition($this->demande->refresh(), RequestStatus::AwaitingSignature, $this->officier);

        $this->projet = $this->redigeLeProjet($this->demande, $this->officier);
        $this->demande->refresh();
    }

    /* ------------------------------------------------------------------ */
    /* Qui peut lire */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function le_maire_de_la_commune_lit_le_projet(): void
    {
        $reponse = $this->actingAs($this->maire)->get(route('acts.draft', $this->projet));

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'application/pdf');
        // `inline` : le maire doit le LIRE avant de décider, pas le ranger
        // dans ses téléchargements.
        $reponse->assertHeader('Content-Disposition', 'inline; filename="projet-acte.pdf"');
        // Laravel reordonne les directives : on verifie leur presence,
        // pas leur ordre, qui ne veut rien dire.
        foreach (['no-store', 'private', 'max-age=0'] as $directive) {
            $this->assertStringContainsString($directive, (string) $reponse->headers->get('Cache-Control'));
        }
        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function le_projet_servi_est_bien_le_projet_et_non_un_acte(): void
    {
        $reponse = $this->actingAs($this->maire)->get(route('acts.draft', $this->projet));

        $texte = $this->extractText($reponse->streamedContent());

        $this->assertStringContainsString(DocumentBuilder::DRAFT_NOTICE, $texte);
        $this->assertStringContainsString($this->officier->name, $texte);
        $this->assertStringNotContainsString(
            'signataire',
            $texte,
            "Un projet n'a pas de signataire : il attend la décision du maire."
        );
    }

    #[Test]
    public function l_officier_du_centre_relit_ce_qu_il_a_redige(): void
    {
        $this->actingAs($this->officier)
            ->get(route('acts.draft', $this->projet))
            ->assertOk();
    }

    /* ------------------------------------------------------------------ */
    /* Qui ne peut pas */
    /* ------------------------------------------------------------------ */

    /** LE refus qui compte : un projet n'est pas l'acte du citoyen. */
    #[Test]
    public function le_citoyen_proprietaire_du_dossier_ne_lit_pas_le_projet(): void
    {
        // Il peut pourtant `view` sa propre demande : c'est bien pour cela
        // qu'une capacité distincte était nécessaire.
        $this->assertTrue($this->citoyen->can('view', $this->demande));

        $this->actingAs($this->citoyen)
            ->get(route('acts.draft', $this->projet))
            ->assertNotFound();
    }

    #[Test]
    public function un_maire_d_une_autre_commune_ne_lit_pas_le_projet(): void
    {
        $autre = User::factory()->mayor(CivilStatusCenter::factory()->create()->commune)->create();

        $this->actingAs($autre)
            ->get(route('acts.draft', $this->projet))
            ->assertNotFound();
    }

    #[Test]
    public function un_officier_d_un_autre_centre_ne_lit_pas_le_projet(): void
    {
        $autre = User::factory()->officer(CivilStatusCenter::factory()->create())->create();

        $this->actingAs($autre)
            ->get(route('acts.draft', $this->projet))
            ->assertNotFound();
    }

    #[Test]
    public function l_administrateur_ne_lit_pas_le_projet(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('acts.draft', $this->projet))
            ->assertNotFound();
    }

    #[Test]
    public function un_visiteur_anonyme_ne_lit_pas_le_projet(): void
    {
        $this->get(route('acts.draft', $this->projet))->assertRedirect(route('login'));
    }

    /* ------------------------------------------------------------------ */
    /* Trace */
    /* ------------------------------------------------------------------ */

    /**
     * La lecture est journalisee.
     *
     * C'est elle qui permettra d'etablir, plus tard, que le maire avait bien le
     * projet sous les yeux avant de signer.
     */
    #[Test]
    public function la_lecture_du_projet_laisse_une_trace(): void
    {
        $this->actingAs($this->maire)->get(route('acts.draft', $this->projet))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->maire->id,
            'action' => 'act.draft_read',
            'auditable_type' => 'act_draft',
            'auditable_id' => $this->projet->id,
        ]);
    }

    #[Test]
    public function un_refus_ne_laisse_aucune_trace_de_lecture(): void
    {
        $this->actingAs($this->citoyen)->get(route('acts.draft', $this->projet));

        $this->assertSame(
            0,
            AuditLog::where('action', 'act.draft_read')->count(),
            'Un refus ne doit pas être journalisé comme une lecture.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* L'écran */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function l_ecran_de_revue_donne_le_projet_a_lire(): void
    {
        $reponse = $this->actingAs($this->maire)->get(route('mayor.review', $this->demande));

        $reponse->assertOk();
        $reponse->assertSee(route('acts.draft', $this->projet));
        // Sans apostrophe dans le motif : Blade l'echappe a certains
        // endroits et pas a d'autres, et un test ne doit pas tomber la-dessus.
        $reponse->assertSee('Lire le projet');
        $reponse->assertSee($this->officier->name);
    }

    /** Sans projet, l'écran le dit au lieu de laisser signer à l'aveugle. */
    #[Test]
    public function sans_projet_l_ecran_de_revue_le_signale(): void
    {
        ActDraft::query()->delete();

        $reponse = $this->actingAs($this->maire)->get(route('mayor.review', $this->demande));

        $reponse->assertOk();
        $reponse->assertSee('Aucun projet');
        $reponse->assertDontSee('Lire le projet');
    }
}
