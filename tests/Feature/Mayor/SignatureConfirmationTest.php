<?php

declare(strict_types=1);

namespace Tests\Feature\Mayor;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\SignatureConfirmation;
use App\Services\VerificationWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\ConfirmsSignature;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * Signer exige de reconfirmer son identite.
 *
 * CE QUE CES TESTS FERMENT (D-069). Jusqu'ici, signer un acte ne demandait
 * qu'une session ouverte. La session officielle dure 30 minutes : un
 * navigateur de maire laisse ouvert permettait a qui passait derriere lui de
 * delivrer des actes d'etat civil. Le §4.3 du brief exige une decision
 * EXPLICITE du maire ; un clic dans une session deja ouverte n'en est pas une.
 *
 * L'ASSERTION QUI COMPTE LE PLUS n'est pas « le refus renvoie une erreur » :
 * c'est « RIEN n'a ete produit ». Un acte ecrit puis annule resterait un acte
 * ecrit.
 */
class SignatureConfirmationTest extends TestCase
{
    use ConfirmsSignature;
    use WritesActDrafts;

    private User $maire;

    private User $officier;

    private User $citoyen;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        RateLimiter::clear('phoenix:signature-confirmation:1');

        $centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($centre)->create();
        $this->maire = User::factory()->mayor($centre->commune)->create();
        $this->equipeLaDoubleAuthentification($this->maire);

        $this->citoyen = User::factory()->citizen()->create();
        $this->citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-820000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $this->citoyen->id,
            'civil_status_center_id' => $centre->id,
            'commune_id' => $centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15', 'place_of_birth' => 'Ville de test',
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
        $this->redigeLeProjet($this->demande, $this->officier);
        $this->demande->refresh();
    }

    /** @param array<string, mixed> $donnees */
    private function signe(array $donnees = []): TestResponse
    {
        return $this->actingAs($this->maire)->post(route('mayor.sign', $this->demande), $donnees);
    }

    private function assertRienProduit(): void
    {
        $this->assertSame(0, DocumentSignature::count(), 'Aucun acte ne doit exister.');
        $this->assertSame(
            RequestStatus::AwaitingSignature,
            $this->demande->refresh()->status,
            'Le dossier ne doit pas avoir changé d’état.'
        );
        $this->assertSame(
            [],
            Storage::disk('private')->files('acts'),
            'Aucun fichier d’acte ne doit avoir été écrit.'
        );
    }

    /* ------------------------------------------------------------------ */

    #[Test]
    public function un_code_valide_signe_l_acte(): void
    {
        $this->signe(['confirmation_code' => $this->codeDeConfirmation($this->maire)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('mayor.dashboard'));

        $this->assertSame(RequestStatus::Signed, $this->demande->refresh()->status);
        $this->assertSame(
            SignatureConfirmation::METHOD_TOTP,
            DocumentSignature::first()->confirmation_method
        );
    }

    /**
     * LE TEST QUI COMPTE : un compte equipe par le VRAI chemin de Fortify.
     *
     * CE QU'IL A ATTRAPE. Ma premiere version du service lisait
     * `$mayor->two_factor_secret` tel quel. Les tests passaient — parce que
     * MON FIXTURE ecrivait le secret en clair. En production, ou Fortify
     * chiffre le secret avant que le cast `encrypted` du modele ne le chiffre
     * une seconde fois, l'accesseur rend du chiffre : aucune signature reelle
     * n'aurait jamais abouti.
     *
     * Ce test n'utilise pas le fixture. Il equipe le compte avec l'action
     * `EnableTwoFactorAuthentication` de Fortify, celle qu'execute l'ecran de
     * securite, puis signe. Si le chemin de lecture diverge de celui de
     * Fortify, il tombe.
     */
    #[Test]
    public function un_compte_equipe_par_le_chemin_reel_de_fortify_signe(): void
    {
        // Le compte est remis a zero avant d'etre equipe : sur un maire actif,
        // la contrainte `users_official_2fa_check` refuserait d'annuler la
        // confirmation, donc on passe par un compte citoyen promu ensuite.
        $frais = User::factory()->citizen()->create();

        app(EnableTwoFactorAuthentication::class)($frais);
        $frais->refresh();

        $secret = Fortify::currentEncrypter()->decrypt($frais->two_factor_secret);

        // Le maire recoit ce meme etat, tel que Fortify l'a ecrit.
        $this->maire->forceFill([
            'two_factor_secret' => $frais->two_factor_secret,
            'two_factor_recovery_codes' => $frais->two_factor_recovery_codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->signe(['confirmation_code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Signed, $this->demande->refresh()->status);
    }

    /** Sans code, rien n'est produit — pas seulement une erreur affichée. */
    #[Test]
    public function sans_code_aucun_acte_n_est_produit(): void
    {
        $this->signe()->assertSessionHasErrors('confirmation_code');

        $this->assertRienProduit();
    }

    #[Test]
    public function un_code_faux_ne_signe_rien(): void
    {
        $this->signe(['confirmation_code' => '000000'])
            ->assertSessionHasErrors('confirmation_code');

        $this->assertRienProduit();
    }

    /**
     * Le code n'est jamais renvoye dans le HTML.
     *
     * Il vaut trente secondes, mais un champ prerempli avec un code
     * d'authentification n'a aucune raison d'exister.
     */
    #[Test]
    public function un_code_refuse_n_est_pas_renvoye_a_la_vue(): void
    {
        $this->signe(['confirmation_code' => '424242'])
            ->assertSessionHasErrors('confirmation_code');

        $this->assertNull(session('_old_input.confirmation_code'));
    }

    /** Le motif saisi, lui, survit au refus : le maire ne le retape pas. */
    #[Test]
    public function le_motif_survit_a_un_code_refuse(): void
    {
        app(RequestTransitionService::class)->transition(
            $this->demande->refresh(), RequestStatus::UnderReview, $this->maire, 'Retour pour contrôle.'
        );
        app(RequestTransitionService::class)->transition(
            $this->demande->refresh(), RequestStatus::Escalated, $this->officier, 'Doute sur la filiation.'
        );

        $this->signe([
            'reason' => 'Pièce complémentaire présentée en mairie et vérifiée ce jour.',
            'confirmation_code' => '000000',
        ])->assertSessionHasErrors('confirmation_code');

        $this->assertSame(
            'Pièce complémentaire présentée en mairie et vérifiée ce jour.',
            session('_old_input.reason')
        );
    }

    /* ------------------------------------------------------------------ */
    /* Code de secours */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function un_code_de_secours_signe_et_se_consomme(): void
    {
        $this->poseDesCodesDeSecours($this->maire, ['AAAA1111-BBBB2222', 'CCCC3333-DDDD4444']);

        $this->signe(['confirmation_code' => 'AAAA1111-BBBB2222'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            SignatureConfirmation::METHOD_RECOVERY,
            DocumentSignature::first()->confirmation_method
        );

        // Consommé : il ne figure plus dans la liste.
        $this->assertNotContains('AAAA1111-BBBB2222', $this->maire->fresh()->recoveryCodes());
        $this->assertContains('CCCC3333-DDDD4444', $this->maire->fresh()->recoveryCodes());
    }

    /* ------------------------------------------------------------------ */
    /* Limitation des essais */
    /* ------------------------------------------------------------------ */

    /**
     * Un code a six chiffres se devine ; sans limite, ce n'est pas une
     * barriere.
     */
    #[Test]
    public function les_essais_sont_limites(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->signe(['confirmation_code' => '000000'])
                ->assertSessionHasErrors('confirmation_code');
        }

        // Le sixième essai est bloqué — même avec le BON code.
        $reponse = $this->signe(['confirmation_code' => $this->codeDeConfirmation($this->maire)]);
        $reponse->assertSessionHasErrors('confirmation_code');

        $this->assertStringContainsString(
            'Trop de codes erronés',
            session('errors')->first('confirmation_code')
        );

        $this->assertRienProduit();
    }

    /* ------------------------------------------------------------------ */
    /* Trace */
    /* ------------------------------------------------------------------ */

    /** Un échec est un signal anti-fraude, pas un incident anodin. */
    #[Test]
    public function un_code_refuse_entre_au_journal(): void
    {
        $this->signe(['confirmation_code' => '000000']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->maire->id,
            'action' => 'act.signature_confirmation_failed',
        ]);
    }

    #[Test]
    public function le_blocage_entre_au_journal(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->signe(['confirmation_code' => '000000']);
        }

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->maire->id,
            'action' => 'act.signature_confirmation_blocked',
        ]);
    }

    #[Test]
    public function une_signature_reussie_n_ecrit_aucun_echec(): void
    {
        $this->signe(['confirmation_code' => $this->codeDeConfirmation($this->maire)])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            AuditLog::where('action', 'act.signature_confirmation_failed')->count()
        );
    }

    /* ------------------------------------------------------------------ */
    /* L'écran */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function l_ecran_de_revue_demande_le_code(): void
    {
        $reponse = $this->actingAs($this->maire)->get(route('mayor.review', $this->demande));

        $reponse->assertOk();
        $reponse->assertSee('confirmation_code', false);
        $reponse->assertSee('Code de confirmation');
    }

    /**
     * Le champ n'est pas `required` en HTML.
     *
     * Le formulaire porte trois boutons ; l'exiger cote navigateur
     * bloquerait aussi « Retourner » et « Rejeter », qui n'en ont pas besoin.
     */
    #[Test]
    public function le_champ_n_est_pas_obligatoire_cote_navigateur(): void
    {
        $html = $this->actingAs($this->maire)
            ->get(route('mayor.review', $this->demande))
            ->getContent();

        preg_match('/<input[^>]*id="confirmation_code"[^>]*>/', (string) $html, $trouve);

        $this->assertNotEmpty($trouve, "Le champ de confirmation est absent de l'écran.");
        $this->assertStringNotContainsString(' required', $trouve[0]);
    }

    /* ------------------------------------------------------------------ */

    /** Retourner un dossier n'exige pas de code : aucun acte n'en sort. */
    #[Test]
    public function retourner_le_dossier_n_exige_aucun_code(): void
    {
        $this->actingAs($this->maire)
            ->post(route('mayor.return', $this->demande), [
                'reason' => "Merci de joindre l'acte de naissance du père.",
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::UnderReview, $this->demande->refresh()->status);
    }

    /**
     * UN MAIRE ACTIF NE PEUT PAS PERDRE SON SECRET — la base le refuse.
     *
     * CE QUE CE TEST A REVELE (D-069). Ma premiere version tentait de vider le
     * secret pour verifier que le service refusait alors de signer. La base a
     * refuse l'ecriture : la contrainte `users_official_2fa_check`, resserree
     * dans la meme journee, exige desormais le secret EN PLUS de la date de
     * confirmation.
     *
     * Elle ne l'exigeait pas avant. Un compte officiel pouvait donc porter une
     * double authentification « confirmee » sans qu'aucun secret n'existe —
     * une protection reputee posee qui n'existait pas. Les comptes de
     * demonstration etaient exactement dans cet etat, et les fabriques de test
     * aussi.
     *
     * Le test verifie donc la barriere qui tient : celle de la base.
     */
    #[Test]
    public function la_base_refuse_un_maire_actif_sans_secret(): void
    {
        $this->expectException(QueryException::class);

        $this->maire->forceFill(['two_factor_secret' => null])->save();
    }

    /**
     * Le service refuse tout de meme, si l'etat lui parvenait.
     *
     * Ceinture et bretelles : la contrainte ci-dessus est la barriere reelle,
     * mais un compte SUSPENDU peut legitimement perdre son secret, et rien ne
     * doit pouvoir signer depuis cet etat.
     */
    #[Test]
    public function un_compte_sans_double_authentification_ne_signe_pas(): void
    {
        $sansSecret = User::factory()->mayor($this->maire->commune)->create();
        $sansSecret->forceFill(['status' => 'suspended', 'two_factor_secret' => null])->save();

        $erreur = null;

        try {
            app(SignatureConfirmation::class)->confirm($sansSecret, '000000');
        } catch (\DomainException $e) {
            $erreur = $e->getMessage();
        }

        $this->assertNotNull($erreur, 'La confirmation aurait dû être refusée.');
        $this->assertStringContainsString('double authentification', $erreur);
    }
}
