<?php

declare(strict_types=1);

namespace Tests\Feature\Mayor;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\ActDraft;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\ActIssuanceService;
use App\Services\DocumentBuilder;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use DomainException;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ReadsPdfText;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * L'officier redige, le maire signe ce qu'il a lu — lecture 2 du diagramme.
 *
 * CE QUE CETTE LECTURE DEPLACE. Le diagramme place « Generate Certificate »
 * chez l'officier. Retenue sur votre arbitrage, elle transfere la
 * responsabilite du CONTENU de l'acte a l'officier, et laisse au maire celle
 * de la delivrance.
 *
 * LE RISQUE QU'ELLE INTRODUIT, et que ces tests ferment : si l'officier redige
 * en amont, il peut modifier le dossier APRES que le maire a lu le projet. Le
 * maire signerait alors autre chose que ce qu'il a vu — un acte dont le
 * contenu lui aurait echappe. C'est precisement ce que le 4.3 du brief
 * interdit.
 *
 * La reponse est une empreinte du CONTENU, relevee a la redaction et
 * recalculee a la signature. Pas une empreinte du PDF : le projet porte un
 * bandeau que l'acte n'a pas, les fichiers different forcement.
 *
 * Voir D-064.
 */
class ActDraftTest extends TestCase
{
    use ReadsPdfText;
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $citoyen;

    private User $officier;

    private User $maire;

    private ReissuanceRequest $demande;

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
            'national_id_number' => 'DEMO-800000001', 'completed_at' => now(),
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
        $this->demande->refresh();
    }

    /** Un projet n'est pas un acte : il le dit, en toutes lettres. */
    #[Test]
    public function le_projet_porte_son_bandeau_et_aucun_signataire(): void
    {
        $projet = $this->redigeLeProjet($this->demande, $this->officier);

        // RELU AVEC pdftotext, et non cherche dans les octets (D-067) : une
        // chaine presente dans le fichier ne prouve pas qu'elle est dessinee
        // sur la page. Elle pourrait n'etre qu'une metadonnee.
        $texte = $this->extractText((string) Storage::disk('private')->get($projet->document_path));

        $this->assertStringContainsString(DocumentBuilder::DRAFT_NOTICE, $texte);
        $this->assertStringContainsString($this->officier->name, $texte);
        $this->assertStringNotContainsString(
            'signataire',
            $texte,
            "Un projet n'a pas de signataire : il attend la décision du maire."
        );
    }

    /** Le projet vit hors de la table des actes signés. */
    #[Test]
    public function un_projet_n_est_jamais_une_signature(): void
    {
        $this->redigeLeProjet($this->demande, $this->officier);

        $this->assertDatabaseCount('act_drafts', 1);
        $this->assertDatabaseCount('document_signatures', 0);
        $this->assertSame(RequestStatus::AwaitingSignature, $this->demande->refresh()->status);
    }

    /** Sans projet, le maire ne signe pas : il ne rédige pas l'acte. */
    #[Test]
    public function sans_projet_la_signature_est_refusee(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches("/Aucun projet d'acte/");

        app(ActIssuanceService::class)->issue($this->demande, $this->maire);
    }

    /** LE test : le dossier modifié après le projet ne peut plus être signé. */
    #[Test]
    public function un_dossier_modifie_apres_le_projet_ne_peut_plus_etre_signe(): void
    {
        $this->redigeLeProjet($this->demande, $this->officier);

        // L'officier modifie le dossier APRÈS que le maire a lu le projet.
        $this->demande->forceFill(['full_name_at_birth' => 'Quelqu’un DE TOUT AUTRE'])->save();

        try {
            app(ActIssuanceService::class)->issue($this->demande->refresh(), $this->maire);
            $this->fail('La signature aurait dû être refusée.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('a changé depuis la rédaction', $e->getMessage());
        }

        // Et rien n'a été produit : ni acte, ni transition.
        $this->assertDatabaseCount('document_signatures', 0);
        $this->assertSame(
            RequestStatus::AwaitingSignature,
            $this->demande->refresh()->status,
            'Le refus doit annuler la transition avec le reste de la transaction.'
        );
    }

    /** Un projet inchangé se signe, et l'acte porte la trace de son rédacteur. */
    #[Test]
    public function l_acte_signe_remonte_a_l_officier_qui_l_a_redige(): void
    {
        $projet = $this->redigeLeProjet($this->demande, $this->officier);

        $signature = app(ActIssuanceService::class)->issue($this->demande->refresh(), $this->maire);

        $this->assertSame($projet->id, $signature->draft_id);
        $this->assertSame($this->maire->id, $signature->mayor_id);
        $this->assertSame(
            $this->officier->id,
            ActDraft::findOrFail($signature->draft_id)->officer_id,
            "La responsabilité du contenu doit remonter nominativement à l'officier."
        );
    }

    /** L'acte signé ne porte PAS le bandeau du projet. */
    #[Test]
    public function l_acte_signe_ne_porte_pas_le_bandeau_de_projet(): void
    {
        $this->redigeLeProjet($this->demande, $this->officier);

        $signature = app(ActIssuanceService::class)->issue($this->demande->refresh(), $this->maire);
        $acte = $this->extractText((string) Storage::disk('private')->get($signature->document_path));

        $this->assertStringNotContainsString(DocumentBuilder::DRAFT_NOTICE, $acte);
        $this->assertStringContainsString('signataire', $acte);
    }

    /**
     * L'empreinte porte sur le CONTENU, pas sur la mise en page.
     *
     * Sans cela, comparer le projet et l'acte serait impossible : ils ne
     * peuvent pas avoir les mêmes octets, puisque l'un porte un bandeau que
     * l'autre n'a pas.
     */
    #[Test]
    public function l_empreinte_ignore_la_mise_en_page_et_suit_le_contenu(): void
    {
        $avant = DocumentBuilder::contentFingerprint($this->demande);

        // Un champ qui ne compose pas l'acte : l'empreinte ne bouge pas.
        $this->demande->forceFill(['last_completed_step' => 4])->save();
        $this->assertSame($avant, DocumentBuilder::contentFingerprint($this->demande->refresh()));

        // Un champ qui compose l'acte : l'empreinte bouge.
        $this->demande->forceFill(['place_of_birth' => 'Une tout autre ville'])->save();
        $this->assertNotSame($avant, DocumentBuilder::contentFingerprint($this->demande->refresh()));
    }

    /**
     * Chaque champ imprimé sur l'acte doit être couvert par l'empreinte.
     *
     * Un champ ajouté à l'acte sans être ajouté à l'empreinte deviendrait
     * modifiable après la lecture du maire, sans que rien ne le signale.
     */
    #[Test]
    public function tout_champ_imprime_sur_l_acte_est_couvert_par_l_empreinte(): void
    {
        // Depuis D-067, le corps commun au projet et à l'acte est un gabarit
        // Blade et non plus une méthode. Le test lit donc le gabarit — et
        // c'est toujours la même question : quels champs sont imprimés ?
        $corps = (string) file_get_contents(
            resource_path('views/documents/partials/body.blade.php')
        );

        preg_match_all('/\$demande->([a-z_]+)/', $corps, $trouves);

        $imprimes = array_unique($trouves[1]);
        $couverts = DocumentBuilder::FINGERPRINTED_FIELDS;

        // `citizen` n'est qu'un accès à la relation, pas un champ imprimé.
        $oublies = array_diff($imprimes, $couverts, ['citizen']);

        $this->assertSame(
            [],
            array_values($oublies),
            'Ces champs figurent sur l’acte mais pas dans l’empreinte : '
                .implode(', ', $oublies)
                .'. Ils seraient modifiables après lecture du maire.'
        );
    }
}
