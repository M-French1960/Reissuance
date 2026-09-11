<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\ActIssuanceService;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * La commande de verification d'une restauration doit ECHOUER quand il le faut.
 *
 * Une commande de verification qui repond toujours « tout va bien » est pire
 * qu'aucune commande : elle donne la certitude a la place de la preuve. Ces
 * tests provoquent donc les defaillances qu'elle est censee detecter.
 *
 * La procedure complete — sauvegarde, restauration dans une autre base,
 * verification — est executee a la main et consignee dans docs/SAUVEGARDE.md :
 * elle suppose mysqldump, le client mysql et une base d'essai, ce qui sort du
 * cadre d'un test unitaire.
 */
class RestoreVerificationTest extends TestCase
{
    use WritesActDrafts;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function acteSigne(): DocumentSignature
    {
        $centre = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($centre)->create();
        $maire = User::factory()->mayor($centre->commune)->create();

        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-990000001', 'completed_at' => now(),
        ]);

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($demande, RequestStatus::UnderReview, $officier);
        $demande->forceFill(['assigned_officer_id' => $officier->id])->save();

        $workflow = app(VerificationWorkflow::class);
        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record($demande->refresh(), $n, $officier, VerificationResult::Match);
        }

        $transitions->transition($demande->refresh(), RequestStatus::AwaitingSignature, $officier);

        // Le maire signe un PROJET établi par l'officier (D-064).
        $this->redigeLeProjet($demande, $officier);
        app(ActIssuanceService::class)->issue($demande->refresh(), $maire);

        return $demande->refresh()->signature;
    }

    #[Test]
    public function une_base_exploitable_est_declaree_exploitable(): void
    {
        $this->acteSigne();

        $this->artisan('phoenix:verifier-restauration')
            ->assertSuccessful();
    }

    /** Le cas produit par une sauvegarde de la base SEULE. */
    #[Test]
    public function un_acte_dont_le_fichier_manque_fait_echouer_la_verification(): void
    {
        $signature = $this->acteSigne();

        Storage::disk('private')->delete($signature->document_path);

        $this->artisan('phoenix:verifier-restauration')
            ->expectsOutputToContain('fichier absent')
            ->assertFailed();
    }

    /** Un octet de difference suffit : l'empreinte ne pardonne pas. */
    #[Test]
    public function un_acte_altere_fait_echouer_la_verification(): void
    {
        $signature = $this->acteSigne();

        $disque = Storage::disk('private');
        $disque->put($signature->document_path, $disque->get($signature->document_path).' ');

        $this->artisan('phoenix:verifier-restauration')
            ->expectsOutputToContain('ne correspondent plus')
            ->assertFailed();
    }

    /**
     * L'echec silencieux : une mauvaise cle d'index ne leve rien, la recherche
     * rend simplement zero resultat.
     */
    #[Test]
    public function une_cle_d_index_qui_ne_correspond_pas_fait_echouer_la_verification(): void
    {
        $this->acteSigne();

        config(['phoenix.blind_index_key' => base64_encode(random_bytes(32))]);

        $this->artisan('phoenix:verifier-restauration')
            ->expectsOutputToContain('BLIND_INDEX_KEY')
            ->assertFailed();
    }

    /** Sans le declencheur, la base accepterait une transition interdite. */
    #[Test]
    public function la_verification_controle_la_presence_du_declencheur(): void
    {
        $this->acteSigne();

        $this->artisan('phoenix:verifier-restauration')
            ->expectsOutputToContain('déclencheur présent')
            ->assertSuccessful();
    }
}
