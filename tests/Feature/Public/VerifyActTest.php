<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\DocumentSignature;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConfirmsSignature;
use Tests\Support\ReadsPdfText;
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * Verifier l'authenticite d'un acte, sans compte (D-088).
 *
 * POURQUOI CETTE PORTE EST PUBLIQUE alors que le suivi d'un dossier ne l'est
 * pas : celui qui verifie n'est pas le demandeur, c'est l'administration,
 * l'ecole ou l'ambassade qui RECOIT une copie et l'a sous les yeux.
 *
 * CE QUE CES TESTS SURVEILLENT, par ordre d'importance :
 *
 * 1. Que la reponse ne fasse fuir aucune identite. Un code seul ne doit
 *    apprendre ni un nom complet, ni une date de naissance, ni une filiation.
 *    C'est ce qui separe une verification d'un annuaire.
 * 2. Que le refus n'apprenne rien : meme reponse pour un code inconnu et pour
 *    un code mal forme.
 * 3. Que la limitation de debit existe reellement, et morde.
 */
class VerifyActTest extends TestCase
{
    use ConfirmsSignature;
    use ReadsPdfText;
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $maire;

    private User $officier;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
        $this->officier = User::factory()->officer($this->centre)->create();
    }

    #[Test]
    public function l_ecran_s_ouvre_sans_compte(): void
    {
        $this->get(route('verify.show'))
            ->assertOk()
            ->assertSee(__('public.verify.title'));
    }

    #[Test]
    public function un_code_valide_confirme_l_authenticite(): void
    {
        $signature = $this->signerUnActe();

        $this->post(route('verify.check'), ['code' => $signature->verification_code])
            ->assertOk()
            ->assertSee(__('public.verify.genuine_title'))
            ->assertSee($signature->request->reference);
    }

    /**
     * LE TEST LE PLUS IMPORTANT DE CE FICHIER.
     *
     * La reponse sert a COMPARER une copie qu'on a sous les yeux, pas a
     * renseigner. Quelqu'un qui aurait seulement un code ne doit apprendre ni
     * le nom du titulaire, ni sa date de naissance, ni sa filiation.
     */
    #[Test]
    public function la_reponse_ne_fait_fuir_aucune_identite(): void
    {
        $signature = $this->signerUnActe();

        $this->post(route('verify.check'), ['code' => $signature->verification_code])
            ->assertOk()
            ->assertDontSee('Personne TRES-RECONNAISSABLE')
            ->assertDontSee('Père TRES-RECONNAISSABLE')
            ->assertDontSee('Mère TRES-RECONNAISSABLE')
            // La date de naissance complete ne sort pas : seule l'annee.
            ->assertDontSee('15/01/1990')
            ->assertDontSee('1990-01-15')
            // Les initiales, elles, permettent la comparaison.
            ->assertSee('P. T.');
    }

    /** Le code se recopie a la main : espaces, tirets et minuscules passent. */
    #[Test]
    public function le_code_est_accepte_quelle_que_soit_sa_typographie(): void
    {
        $signature = $this->signerUnActe();
        $recopie = strtolower(implode(' ', str_split((string) $signature->verification_code, 4)));

        $this->post(route('verify.check'), ['code' => $recopie])
            ->assertOk()
            ->assertSee(__('public.verify.genuine_title'));
    }

    /**
     * Un refus n'apprend rien.
     *
     * Meme reponse pour un code inconnu et pour un code mal forme : sinon la
     * page dirait si un code « existe presque », ce qui guiderait une
     * recherche.
     */
    #[Test]
    public function un_code_inconnu_et_un_code_mal_forme_rendent_la_meme_reponse(): void
    {
        $inconnu = $this->post(route('verify.check'), ['code' => 'ZZZZ-ZZZZ-ZZZZ']);
        $malForme = $this->post(route('verify.check'), ['code' => '???']);

        $inconnu->assertOk()->assertSee(__('public.verify.unknown_title'));
        $malForme->assertOk()->assertSee(__('public.verify.unknown_title'));

        $this->assertSame(
            $inconnu->getStatusCode(),
            $malForme->getStatusCode(),
            'Un code mal formé ne doit pas se distinguer d’un code inconnu.'
        );
    }

    #[Test]
    public function un_code_vide_n_affirme_rien(): void
    {
        $this->post(route('verify.check'), ['code' => ''])
            ->assertOk()
            ->assertSee(__('public.verify.unknown_title'))
            ->assertDontSee(__('public.verify.genuine_title'));
    }

    /** La limitation de debit est posee sur la route, pas dans le controleur. */
    #[Test]
    public function la_route_est_limitee_en_debit(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn ($r): bool => $r->getName() === 'verify.check'
        );

        $this->assertNotNull($route);
        $this->assertContains(
            'throttle:10,1',
            $route->gatherMiddleware(),
            'La vérification publique doit être limitée en débit.'
        );
    }

    /**
     * ET ELLE MORD.
     *
     * Le test structurel ci-dessus serait satisfait par un middleware pose
     * avec un quota absurde. Celui-ci exerce le comportement : au onzieme
     * essai dans la minute, la porte se ferme.
     */
    #[Test]
    public function au_dela_du_quota_les_essais_sont_refuses(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('verify.check'), ['code' => 'ZZZZ-ZZZZ-ZZZZ'])->assertOk();
        }

        $this->post(route('verify.check'), ['code' => 'ZZZZ-ZZZZ-ZZZZ'])->assertStatus(429);
    }

    /**
     * Seules les verifications qui ABOUTISSENT sont journalisees.
     *
     * Enregistrer chaque tentative ferait d'une table en ajout seul un levier
     * pour la remplir depuis l'exterieur.
     */
    #[Test]
    public function seule_une_verification_qui_aboutit_est_journalisee(): void
    {
        $signature = $this->signerUnActe();

        $this->post(route('verify.check'), ['code' => 'ZZZZ-ZZZZ-ZZZZ']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'act.verified']);

        $this->post(route('verify.check'), ['code' => $signature->verification_code]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'act.verified',
            'auditable_type' => 'document_signature',
            'auditable_id' => $signature->id,
            'actor_id' => null,
        ]);
    }

    /** Un acte de demonstration est authentique ET sans valeur juridique. */
    #[Test]
    public function un_acte_de_demonstration_le_dit(): void
    {
        $signature = $this->signerUnActe();

        $this->post(route('verify.check'), ['code' => $signature->verification_code])
            ->assertOk()
            ->assertSee(__('public.verify.genuine_title'))
            ->assertSee(__('public.verify.demo_title'));
    }

    /** Chaque acte porte son propre code. */
    #[Test]
    public function deux_actes_portent_deux_codes_differents(): void
    {
        $premier = $this->signerUnActe();

        // Un SECOND maire, et non le meme deux fois : le code de confirmation
        // est un TOTP, et le rejouer dans la meme fenetre de 30 s est refuse.
        // C'est une protection du produit, pas un obstacle du test — deux
        // maires d'une meme commune est d'ailleurs le cas reel (un adjoint).
        $second = $this->signerUnActe(User::factory()->mayor($this->centre->commune)->create());

        $this->assertNotSame($premier->verification_code, $second->verification_code);
        $this->assertSame(
            DocumentSignature::VERIFICATION_LENGTH,
            strlen((string) $premier->verification_code)
        );
    }

    /** Le code est imprime SUR l'acte : sans cela, personne ne peut verifier. */
    #[Test]
    public function le_code_est_imprime_sur_l_acte(): void
    {
        $signature = $this->signerUnActe();

        $texte = $this->extractText(
            (string) Storage::disk('private')->get($signature->document_path)
        );

        $this->assertStringContainsString(
            $signature->formattedVerificationCode(),
            $texte,
            "Le code de vérification doit être imprimé sur l'acte."
        );
    }

    /** L'alphabet exclut les caracteres qui se confondent quand on recopie. */
    #[Test]
    public function l_alphabet_du_code_exclut_les_caracteres_ambigus(): void
    {
        foreach (['O', '0', 'I', '1'] as $ambigu) {
            $this->assertStringNotContainsString(
                $ambigu,
                DocumentSignature::VERIFICATION_ALPHABET,
                "Le caractère {$ambigu} se confond avec un autre quand on recopie un code."
            );
        }
    }

    /**
     * Mene une demande jusqu'a la signature, et rend la signature produite.
     *
     * Le nom de naissance est volontairement reconnaissable : c'est lui que le
     * test de fuite cherche.
     */
    private function signerUnActe(?User $maire = null): DocumentSignature
    {
        $maire ??= $this->maire;
        $citoyen = User::factory()->citizen()->create();

        $demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne TRES-RECONNAISSABLE',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père TRES-RECONNAISSABLE', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère TRES-RECONNAISSABLE', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($demande, RequestStatus::UnderReview, $this->officier);
        $demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();

        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 2, 3, 4, 5] as $n) {
            $workflow->record($demande, $n, $this->officier, VerificationResult::Match);
        }
        $demande->refresh();

        $transitions->transition($demande, RequestStatus::AwaitingSignature, $this->officier);
        $this->redigeLeProjet($demande, $this->officier);
        $demande->refresh();

        $this->actingAs($maire)->post(route('mayor.sign', $demande), [
            'confirmation_code' => $this->codeDeConfirmation($maire),
        ])->assertSessionHasNoErrors();

        // La verification publique se fait SANS compte : on quitte la session
        // du maire, sinon les tests suivants passeraient authentifies.
        auth()->logout();
        $this->flushSession();

        return $demande->refresh()->signature()->firstOrFail();
    }
}
