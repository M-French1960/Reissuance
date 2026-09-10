<?php

declare(strict_types=1);

namespace Tests\Feature\Officer;

use App\Contracts\FacialRecognitionProvider;
use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Integrations\Real\BiometricFacialRecognitionProvider;
use App\Models\CivilStatusCenter;
use App\Models\FacialComparison;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use App\Support\ProviderOutcome;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Comparaison faciale — obligatoire, et sans pouvoir de decision.
 *
 * Deux proprietes comptent plus que tout le reste, et ce sont elles que ces
 * tests protegent :
 *
 *   1. La comparaison DOIT avoir eu lieu avant que l'officier ne conclue.
 *   2. Une personne que la machine ne reconnait pas n'est JAMAIS bloquee.
 *      Sans cette porte, un faux negatif priverait quelqu'un de son acte
 *      d'etat civil, donc de l'acces a a peu pres tout.
 */
class FacialRecognitionTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
    }

    /** @param  string  $numero  le prefixe pilote l'adaptateur factice */
    private function dossier(string $numero): ReissuanceRequest
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => $numero, 'completed_at' => now(),
        ]);

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $demande->forceFill(['submitted_at' => now()])->save();

        foreach (['selfie', 'id_document'] as $type) {
            $this->actingAs($citoyen)->post(
                route('citizen.requests.attachments.store', $demande),
                ['kind' => $type, 'file' => UploadedFile::fake()->image("{$type}.jpg", 800, 600)]
            );
        }

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($demande->refresh(), RequestStatus::UnderReview, $this->officier);
        $demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();

        return $demande->refresh()->load('attachments');
    }

    /* ------------------------------------------------------- obligatoire */

    #[Test]
    public function l_etape_3_est_refusee_tant_que_la_comparaison_n_a_pas_eu_lieu(): void
    {
        $demande = $this->dossier('DEMO-FACE-000001');

        $this->actingAs($this->officier)
            ->post(route('officer.verification.acknowledge', [$demande, 3]), ['result' => 'match'])
            ->assertSessionHasErrors('result');

        $this->assertNull(app(VerificationWorkflow::class)->steps($demande)->get(3));
    }

    #[Test]
    public function une_fois_la_comparaison_lancee_l_officier_peut_conclure(): void
    {
        $demande = $this->dossier('DEMO-FACE-000002');

        $this->actingAs($this->officier)
            ->post(route('officer.verification.facial', $demande))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->officier)
            ->post(route('officer.verification.acknowledge', [$demande, 3]), ['result' => 'match'])
            ->assertSessionHasNoErrors();

        $etape = app(VerificationWorkflow::class)->steps($demande->refresh())->get(3);
        $this->assertSame(VerificationResult::Match, $etape->result);
    }

    /* ------------------------------------ la machine ne decide pas */

    /**
     * LE test le plus important du lot.
     *
     * La machine dit « ce n'est pas la meme personne ». L'officier, qui a les
     * photographies sous les yeux, conclut l'inverse. Il doit pouvoir le
     * faire, en motivant — sans quoi un faux negatif serait une exclusion
     * administrative sans recours.
     */
    #[Test]
    public function un_officier_peut_conclure_a_l_inverse_de_la_machine(): void
    {
        $demande = $this->dossier('DEMO-VISAGE-KO-01');

        $this->actingAs($this->officier)->post(route('officer.verification.facial', $demande));

        $avis = app(VerificationWorkflow::class)->facialOpinion($demande->refresh());
        $this->assertSame(ProviderOutcome::NoMatch->value, $avis['outcome']);

        $this->actingAs($this->officier)
            ->post(route('officer.verification.acknowledge', [$demande, 3]), [
                'result' => 'match',
                'note' => "Photographie ancienne ; la personne est venue au guichet et j'ai vu la pièce originale.",
            ])->assertSessionHasNoErrors();

        $etape = app(VerificationWorkflow::class)->steps($demande->refresh())->get(3);

        $this->assertSame(VerificationResult::Match, $etape->result);
        // L'avis contraire de la machine est recopie dans l'etape : on saura
        // que l'officier est passe outre, et sur quoi.
        $this->assertSame('no_match', $etape->payload['facial_outcome']);
    }

    /**
     * Le service tombe : la demande n'est pas bloquee pour autant.
     *
     * Une panne de comparaison faciale ne doit pas suspendre la delivrance des
     * actes d'etat civil d'un centre entier.
     */
    #[Test]
    public function une_panne_du_service_ne_bloque_pas_la_verification(): void
    {
        $demande = $this->dossier('DEMO-VISAGE-PANNE-1');

        $this->actingAs($this->officier)
            ->post(route('officer.verification.facial', $demande))
            ->assertSessionHasNoErrors();

        $avis = app(VerificationWorkflow::class)->facialOpinion($demande->refresh());
        $this->assertSame(ProviderOutcome::Unavailable->value, $avis['outcome']);

        $this->actingAs($this->officier)
            ->post(route('officer.verification.acknowledge', [$demande, 3]), [
                'result' => 'match',
                'note' => 'Comparaison indisponible ; examen visuel fait au guichet.',
            ])->assertSessionHasNoErrors();

        $this->assertSame(
            VerificationResult::Match,
            app(VerificationWorkflow::class)->steps($demande->refresh())->get(3)->result
        );
    }

    /* --------------------------------------------- ce qui est conserve */

    /**
     * Aucun gabarit biometrique n'est stocke.
     *
     * On garde l'avis et le score. Un encodage de visage serait une donnee
     * biometrique conservee, avec tout ce que cela implique.
     */
    #[Test]
    public function aucun_gabarit_biometrique_n_est_conserve(): void
    {
        $demande = $this->dossier('DEMO-FACE-000003');

        $this->actingAs($this->officier)->post(route('officer.verification.facial', $demande));

        $ligne = FacialComparison::where('request_id', $demande->id)->firstOrFail();
        $brut = json_encode($ligne->payload, JSON_UNESCAPED_UNICODE);

        foreach (['embedding', 'template', 'descriptor', 'landmarks', 'encoding', 'image', 'base64'] as $interdit) {
            $this->assertStringNotContainsString($interdit, strtolower((string) $brut),
                "La charge conservée contient « {$interdit} » : un gabarit biométrique ne doit pas être stocké.");
        }

        $this->assertNotNull($ligne->similarity());
    }

    /** Le journal porte l'issue, jamais une donnee personnelle. */
    #[Test]
    public function le_journal_ne_porte_que_l_issue_de_la_comparaison(): void
    {
        $demande = $this->dossier('DEMO-FACE-000004');

        $this->actingAs($this->officier)->post(route('officer.verification.facial', $demande));

        $ligne = \DB::table('audit_logs')
            ->where('action', 'verification.facial_comparison_run')
            ->where('auditable_id', $demande->id)
            ->firstOrFail();

        $this->assertSame(ProviderOutcome::Match->label(), $ligne->reason);
        $this->assertStringNotContainsString('DEMO-FACE', (string) $ligne->reason);
    }

    /* ------------------------------------------------------- adaptateurs */

    #[Test]
    public function le_squelette_reel_leve_une_exception_explicite(): void
    {
        $demande = $this->dossier('DEMO-FACE-000005');

        $this->app->bind(FacialRecognitionProvider::class, fn () => new BiometricFacialRecognitionProvider);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/BIOMETRIE/');

        app(VerificationWorkflow::class)->runFacialComparison($demande, $this->officier);
    }

    #[Test]
    public function la_comparaison_exige_les_deux_photographies(): void
    {
        $demande = $this->dossier('DEMO-FACE-000006');
        $demande->attachments()->where('kind', 'selfie')->delete();

        $this->actingAs($this->officier)
            ->post(route('officer.verification.facial', $demande->refresh()->load('attachments')))
            ->assertSessionHasErrors('provider');
    }

    /** Les declencheurs resistent a la ponctuation du numero (D-021). */
    #[Test]
    public function les_declencheurs_resistent_a_la_ponctuation(): void
    {
        foreach (['DEMO-VISAGE-KO-7', 'demo visage ko 8', 'DeMo.ViSaGe.Ko.9'] as $ecriture) {
            $demande = $this->dossier($ecriture);

            $this->actingAs($this->officier)->post(route('officer.verification.facial', $demande));

            $this->assertSame(
                ProviderOutcome::NoMatch->value,
                app(VerificationWorkflow::class)->facialOpinion($demande->refresh())['outcome'],
                "L'écriture « {$ecriture} » n'a pas déclenché le cas attendu."
            );
        }
    }
}
