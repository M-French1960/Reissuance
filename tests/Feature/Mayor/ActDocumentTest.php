<?php

declare(strict_types=1);

namespace Tests\Feature\Mayor;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\ActIssuanceService;
use App\Services\DocumentBuilder;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsPdfText;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * L'acte produit et sa preuve.
 *
 * Le test central du jalon : un document produit par l'adaptateur factice ne
 * doit JAMAIS pouvoir être confondu avec un acte authentique. Vérifié en
 * relisant le PDF, pas en supposant que la mention y est.
 */
class ActDocumentTest extends TestCase
{
    use ReadsPdfText;
    use WritesActDrafts;

    private User $maire;

    private User $officier;

    private User $citoyen;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $centre = CivilStatusCenter::factory()->create();
        $this->maire = User::factory()->mayor($centre->commune)->create();
        $this->officier = User::factory()->officer($centre)->create();

        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test', 'copies_requested' => 1,
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);

        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 2, 3, 4, 5] as $n) {
            $workflow->record($this->demande, $n, $this->officier, VerificationResult::Match);
        }

        $transitions->transition($this->demande->refresh(), RequestStatus::AwaitingSignature, $this->officier);

        // Le maire signe un PROJET établi par l'officier (D-064).
        $this->redigeLeProjet($this->demande, $this->officier);
        $this->demande->refresh();
    }

    private function issue(): DocumentSignature
    {
        return app(ActIssuanceService::class)->issue($this->demande, $this->maire);
    }

    /**
     * UNE ADRESSE LONGUE TIENT DANS LA PAGE.
     *
     * CE QUE CE TEST A ATTRAPE (D-067). L'ancien generateur PDF, ecrit a la
     * main faute d'avoir pu installer dompdf, ne savait pas revenir a la
     * ligne : il posait le texte a une abscisse calculee et n'en verifiait
     * jamais la largeur. Une adresse de parents de 88 caracteres — une adresse
     * camerounaise ordinaire, avec quartier, rue, repere et boite postale —
     * sortait de la page et disparaissait a l'impression. Releve avant
     * correction, sur ce meme libelle, pdftotext rendait :
     *
     *     Adresse des parents :   Quartier Nkolbisson, rue des Manguiers,
     *     derriere ecole publique, BP 15243 Yaound
     *
     * Le nom de la ville etait coupe en deux. Sur un acte d'etat civil, ce
     * n'est pas un defaut d'affichage : c'est un document faux.
     *
     * L'adresse est posee AVANT la redaction du projet, sinon l'empreinte de
     * contenu refuserait la signature — a juste titre.
     */
    #[Test]
    public function une_adresse_longue_tient_entierement_dans_l_acte(): void
    {
        $adresse = 'Quartier Nkolbisson, rue des Manguiers, derriere ecole publique, '
            .'BP 15243 Yaounde Centre';

        $this->demande->forceFill(['parents_address' => $adresse])->save();
        $this->redigeLeProjet($this->demande->refresh(), $this->officier);

        $signature = $this->issue();
        $texte = $this->extractText((string) Storage::disk('private')->get($signature->document_path));

        // Le texte extrait porte des retours a la ligne : on compare mot a
        // mot plutot que sur la chaine entiere, qui est coupee par la mise en
        // page et non par la page.
        foreach (['Nkolbisson', 'Manguiers', 'publique', '15243', 'Yaounde', 'Centre'] as $mot) {
            $this->assertStringContainsString(
                $mot,
                $texte,
                "« {$mot} » manque a l'acte : l'adresse deborde encore de la page."
            );
        }
    }

    #[Test]
    public function l_acte_produit_est_un_pdf_valide(): void
    {
        $signature = $this->issue();
        $pdf = (string) Storage::disk('private')->get($signature->document_path);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);

        $texte = $this->extractText($pdf);
        $this->assertStringContainsString("EXTRAIT D'ACTE DE NAISSANCE", $texte);
        $this->assertStringContainsString('Personne DE TEST', $texte);
        $this->assertStringContainsString($this->demande->reference, $texte);
        $this->assertStringContainsString($this->maire->name, $texte);
    }

    /**
     * LE test du jalon. Aucun document produit par l'adaptateur factice ne
     * doit pouvoir passer pour un acte authentique.
     */
    #[Test]
    public function l_acte_de_demonstration_porte_la_mention_sans_valeur_juridique(): void
    {
        $signature = $this->issue();
        $texte = $this->extractText((string) Storage::disk('private')->get($signature->document_path));

        $this->assertStringContainsString(DocumentBuilder::DEMO_NOTICE, $texte);
        $this->assertStringContainsString('SANS VALEUR JURIDIQUE', $texte);
        $this->assertStringContainsString('ne peut etre presente a aucune administration', $texte);
    }

    /** La mention figure AUSSI en haut de la première page : impossible à manquer. */
    #[Test]
    public function la_mention_figure_des_la_premiere_ligne(): void
    {
        $signature = $this->issue();
        $texte = $this->extractText((string) Storage::disk('private')->get($signature->document_path));

        $lignes = array_values(array_filter(array_map('trim', explode("\n", $texte)), fn ($l) => $l !== ''));

        $this->assertSame(
            DocumentBuilder::DEMO_NOTICE,
            $lignes[0] ?? '',
            'La mention doit être la toute première ligne du document.'
        );
    }

    #[Test]
    public function la_preuve_de_signature_est_produite_et_lisible(): void
    {
        $signature = $this->issue();
        $texte = $this->extractText((string) Storage::disk('private')->get($signature->proof_path));

        $this->assertStringContainsString('PREUVE DE SIGNATURE', $texte);
        $this->assertStringContainsString($signature->document_hash, str_replace("\n", '', $texte));
        $this->assertStringContainsString('NON - demonstration', $texte);
        $this->assertStringContainsString(DocumentBuilder::DEMO_NOTICE, $texte);
    }

    /** L'empreinte porte sur le document tel qu'il est stocké. */
    #[Test]
    public function l_empreinte_correspond_au_document_stocke(): void
    {
        $signature = $this->issue();
        $pdf = (string) Storage::disk('private')->get($signature->document_path);

        $this->assertSame(hash('sha256', $pdf), $signature->document_hash);
        $this->assertSame(64, strlen($signature->document_hash));
    }

    #[Test]
    public function le_citoyen_telecharge_son_acte_et_la_consultation_est_journalisee(): void
    {
        $signature = $this->issue();

        $this->actingAs($this->citoyen)
            ->get(route('acts.document', $signature))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="acte.pdf"');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->citoyen->id,
            'action' => 'act.document_downloaded',
            'auditable_id' => $signature->id,
        ]);
    }

    /**
     * Le refus est un 404 et non un 403 : les identifiants de signature sont
     * sequentiels, et un 403 confirmerait qu'un acte porte ce numero — donc
     * renseignerait sur le volume d'actes delivres.
     */
    #[Test]
    public function un_autre_citoyen_ne_telecharge_pas_l_acte(): void
    {
        $signature = $this->issue();
        $intrus = User::factory()->citizen()->create();

        $this->actingAs($intrus)->get(route('acts.document', $signature))->assertNotFound();
        $this->actingAs($intrus)->get(route('acts.proof', $signature))->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $intrus->id, 'action' => 'act.document_downloaded',
        ]);
    }

    /** L'administrateur reste exclu de tout contenu de dossier (§4.2). */
    #[Test]
    public function l_administrateur_ne_telecharge_pas_l_acte(): void
    {
        $signature = $this->issue();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('acts.document', $signature))
            ->assertNotFound();
    }

    /** Les fichiers vivent hors de la racine web, sous des chemins opaques. */
    #[Test]
    public function les_documents_sont_hors_de_la_racine_web(): void
    {
        $signature = $this->issue();

        foreach ([$signature->document_path, $signature->proof_path] as $chemin) {
            $this->assertMatchesRegularExpression('#^acts/[0-9a-f-]{36}/[a-z]+\.pdf$#', $chemin);
            $this->assertFalse(file_exists(public_path($chemin)));
            $this->assertStringNotContainsString($this->demande->reference, $chemin);
            $this->assertStringNotContainsString('DE TEST', $chemin);
        }
    }
}
