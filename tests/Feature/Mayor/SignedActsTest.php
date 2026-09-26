<?php

declare(strict_types=1);

namespace Tests\Feature\Mayor;

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
use Tests\Support\WritesActDrafts;
use Tests\TestCase;

/**
 * « Ce que vous avez signe » (D-086).
 *
 * CE QUE CES TESTS SURVEILLENT. L'ecran a demande d'OUVRIR une portee de
 * visibilite, ce qui est l'operation la plus risquee du projet. L'ouverture est
 * censee etre aussi etroite que possible : l'etat « signe », la commune du
 * maire, et une signature enregistree a SON nom. Chacune de ces trois
 * conditions a son test, et deux d'entre eux verifient un REFUS — ce sont eux
 * qui prouvent que l'ouverture n'a pas debordé.
 *
 * Le test le plus important est celui de l'adjoint : un second maire de la
 * meme commune ne doit pas voir l'acte signe par son collegue.
 */
class SignedActsTest extends TestCase
{
    use ConfirmsSignature;
    use WritesActDrafts;

    private CivilStatusCenter $centre;

    private User $maire;

    private User $officier;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->maire = User::factory()->mayor($this->centre->commune)->create();
        $this->officier = User::factory()->officer($this->centre)->create();
    }

    #[Test]
    public function l_ecran_liste_ce_que_le_maire_a_signe(): void
    {
        $this->signerUnActe();

        $this->actingAs($this->maire)
            ->get(route('mayor.signed'))
            ->assertOk()
            ->assertSee($this->demande->reference)
            ->assertSee('Personne TRES-RECONNAISSABLE');
    }

    /**
     * LE TEST LE PLUS IMPORTANT DE CE FICHIER.
     *
     * L'ouverture de portee est limitee aux signatures enregistrees au nom du
     * maire connecte. Un adjoint, ou un successeur, de la MEME commune ne doit
     * pas voir l'acte signe par son collegue. Si ce test tombe, l'ouverture a
     * deborde d'un maire a toute une commune.
     */
    #[Test]
    public function un_autre_maire_de_la_meme_commune_ne_voit_pas_cet_acte(): void
    {
        $this->signerUnActe();

        $adjoint = User::factory()->mayor($this->centre->commune)->create();

        // La session du test est partagee entre les deux acteurs : le message
        // de confirmation laisse par la signature y porte la reference, et
        // ferait echouer l'assertion pour une raison qui n'existe pas en
        // production, ou chaque compte a sa propre session.
        $this->flushSession();

        $this->actingAs($adjoint)
            ->get(route('mayor.signed'))
            ->assertOk()
            ->assertDontSee($this->demande->reference)
            ->assertDontSee('Personne TRES-RECONNAISSABLE');

        // Et pas seulement dans l'ecran : la portee elle-meme doit le masquer.
        $this->actingAs($adjoint);
        $this->assertNull(
            ReissuanceRequest::find($this->demande->id),
            'La portée doit masquer un acte signé par un collègue.'
        );
    }

    /** La portee rend bien l'acte a celui qui l'a signe. */
    #[Test]
    public function la_portee_rend_l_acte_a_son_signataire(): void
    {
        $this->signerUnActe();

        $this->actingAs($this->maire);

        $this->assertSame(
            $this->demande->id,
            ReissuanceRequest::find($this->demande->id)?->id,
            'Le signataire doit voir ce qu’il a signé.'
        );
    }

    /**
     * L'ouverture n'accorde AUCUNE action.
     *
     * Elle rend une demande visible, rien de plus : `sign` exige toujours
     * « en attente de signature » ou « escaladee ».
     */
    #[Test]
    public function voir_un_acte_signe_ne_permet_pas_de_le_resigner(): void
    {
        $this->signerUnActe();

        $this->actingAs($this->maire)
            ->post(route('mayor.sign', $this->demande), [
                'confirmation_code' => $this->codeDeConfirmation($this->maire),
            ])
            ->assertForbidden();
    }

    /**
     * LA SIGNATURE FERME LA PIECE D'IDENTITE.
     *
     * Sans cette regle, l'ouverture de `view()` serait heritee par
     * `viewIdentityDocuments()` et la photo d'identite du citoyen resterait
     * consultable par le maire indefiniment, longtemps apres sa decision.
     */
    #[Test]
    public function la_piece_d_identite_se_ferme_apres_la_signature(): void
    {
        $this->signerUnActe();

        $this->assertFalse(
            $this->maire->can('viewIdentityDocuments', $this->demande),
            "La pièce d'identité ne doit plus être accessible après la signature."
        );
    }

    /** Le signataire peut relire l'acte qui porte sa signature. */
    #[Test]
    public function le_signataire_peut_retelecharger_son_acte(): void
    {
        $signature = $this->signerUnActe();

        $this->actingAs($this->maire)
            ->get(route('acts.document', $signature))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** Un autre maire ne retelecharge pas l'acte de son collegue : 404. */
    #[Test]
    public function un_autre_maire_ne_retelecharge_pas_l_acte_d_un_collegue(): void
    {
        $signature = $this->signerUnActe();

        $this->actingAs(User::factory()->mayor($this->centre->commune)->create())
            ->get(route('acts.document', $signature))
            ->assertNotFound();
    }

    /**
     * L'ecran ne montre pas d'etat de delivrance, parce qu'il n'en existe pas.
     *
     * Ce test tombe le jour ou quelqu'un ajoute une colonne « Delivree » pour
     * ressembler a la maquette, sans que rien en base ne la renseigne.
     */
    #[Test]
    public function l_ecran_n_invente_pas_un_etat_de_delivrance(): void
    {
        $this->signerUnActe();

        $reponse = $this->actingAs($this->maire)->get(route('mayor.signed'));

        $reponse->assertOk()
            ->assertDontSee('Délivrée')
            ->assertDontSee('À retirer')
            ->assertSee(__('mayor.signed.limits_delivery'));
    }

    /** Aucun export : une liste nominative ne sort pas de la plateforme. */
    #[Test]
    public function l_ecran_ne_propose_aucun_export(): void
    {
        $this->signerUnActe();

        $this->actingAs($this->maire)
            ->get(route('mayor.signed'))
            ->assertOk()
            ->assertDontSee('CSV')
            ->assertSee(__('mayor.signed.limits_export'));
    }

    /** Un officier n'atteint pas l'historique des signatures du maire. */
    #[Test]
    public function un_officier_n_atteint_pas_cet_ecran(): void
    {
        $this->actingAs($this->officier)->get(route('mayor.signed'))->assertForbidden();
    }

    /** Une periode inventee retombe sur la valeur par defaut. */
    #[Test]
    public function une_periode_inventee_retombe_sur_la_valeur_par_defaut(): void
    {
        $this->actingAs($this->maire)
            ->get(route('mayor.signed', ['semaines' => 999]))
            ->assertOk()
            ->assertSee(__('mayor.signed.weeks', ['count' => 8]));
    }

    /**
     * Mene une demande jusqu'a la signature, et rend la signature produite.
     *
     * Le nom de naissance est volontairement reconnaissable : c'est lui que les
     * tests de refus cherchent.
     */
    private function signerUnActe(): DocumentSignature
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-600000001', 'completed_at' => now(),
        ]);

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne TRES-RECONNAISSABLE',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père DE TEST', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère DE TEST', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();

        $workflow = app(VerificationWorkflow::class);
        foreach ([1, 2, 3, 4, 5] as $n) {
            $workflow->record($this->demande, $n, $this->officier, VerificationResult::Match);
        }
        $this->demande->refresh();

        $transitions->transition($this->demande, RequestStatus::AwaitingSignature, $this->officier);
        $this->redigeLeProjet($this->demande, $this->officier);
        $this->demande->refresh();

        $this->actingAs($this->maire)->post(route('mayor.sign', $this->demande), [
            'confirmation_code' => $this->codeDeConfirmation($this->maire),
        ])->assertSessionHasNoErrors();

        $this->demande->refresh();

        return $this->demande->signature()->firstOrFail();
    }
}
