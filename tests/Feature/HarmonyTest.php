<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Notifications\RequestAwaitsMayor;
use App\Services\RequestTransitionService;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que le parcours complet relie, et qui ne l'etait pas (D-089).
 *
 * CE QUE CES TESTS SURVEILLENT. Chacun ferme une rupture entre ce que le
 * serveur fait et ce que l'interface en montre — des defauts qu'aucun test
 * unitaire ne voyait, parce que chaque cote, pris seul, etait correct :
 *
 * 1. Le maire n'etait prevenu de rien. La machine a etats posait un dossier en
 *    « en attente de signature », et le seul a pouvoir le debloquer devait
 *    rafraichir son tableau de bord pour l'apprendre.
 * 2. La page publique de verification n'etait atteignable par AUCUN lien.
 * 3. Un dossier qui attend le demandeur etait indiscernable, dans la file de
 *    l'officier, d'un dossier en souffrance.
 */
class HarmonyTest extends TestCase
{
    private CivilStatusCenter $centre;

    private User $officier;

    private User $citoyen;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = CivilStatusCenter::factory()->create();
        $this->officier = User::factory()->officer($this->centre)->create();
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
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $this->citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $this->officier);
        $this->demande->forceFill(['assigned_officer_id' => $this->officier->id])->save();
        $this->demande->refresh();
    }

    /**
     * LE MAIRE EST PREVENU QUAND UN DOSSIER L'ATTEND.
     *
     * C'est le manque le plus couteux des trois : un dossier pouvait dormir
     * dans son parapheur sans que rien ne le signale, pendant que le demandeur
     * attendait.
     */
    #[Test]
    public function le_maire_est_prevenu_quand_un_dossier_attend_sa_signature(): void
    {
        Notification::fake();
        $maire = User::factory()->mayor($this->centre->commune)->create();

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );

        Notification::assertSentTo($maire, RequestAwaitsMayor::class);
    }

    #[Test]
    public function le_maire_est_prevenu_d_une_escalade(): void
    {
        Notification::fake();
        $maire = User::factory()->mayor($this->centre->commune)->create();

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::Escalated, $this->officier, 'Doute sur la pièce.'
        );

        Notification::assertSentTo(
            $maire,
            RequestAwaitsMayor::class,
            fn (RequestAwaitsMayor $n): bool => $n->escalade === true
        );
    }

    /** Un maire d'une AUTRE commune n'est pas derange. */
    #[Test]
    public function le_maire_d_une_autre_commune_n_est_pas_prevenu(): void
    {
        Notification::fake();
        $ailleurs = User::factory()->mayor(CivilStatusCenter::factory()->create()->commune)->create();

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );

        Notification::assertNotSentTo($ailleurs, RequestAwaitsMayor::class);
    }

    /** Un maire suspendu ne signe rien : le prevenir ne servirait a personne. */
    #[Test]
    public function un_maire_suspendu_n_est_pas_prevenu(): void
    {
        Notification::fake();
        $suspendu = User::factory()->mayor($this->centre->commune)->create(['status' => 'suspended']);

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );

        Notification::assertNotSentTo($suspendu, RequestAwaitsMayor::class);
    }

    /**
     * Le texte envoye au maire n'est pas celui du demandeur.
     *
     * `RequestStatusChanged` dit « VOTRE demande a ete acceptee ». Servi au
     * maire, ce texte lui ferait lire « votre demande » a propos du dossier
     * d'un tiers.
     */
    #[Test]
    public function le_maire_ne_recoit_pas_le_texte_ecrit_pour_le_demandeur(): void
    {
        $maire = User::factory()->mayor($this->centre->commune)->create();

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $this->officier
        );

        $recue = $maire->notifications()->latest('created_at')->first();

        $this->assertNotNull($recue, 'Le maire doit recevoir une notification.');
        $this->assertSame(
            __('notifications.titles.mayor_awaiting'),
            $recue->data['title'] ?? null,
        );
        $this->assertStringNotContainsString(
            (string) __('notifications.bodies.awaiting_signature', ['reference' => $this->demande->reference]),
            (string) ($recue->data['body'] ?? ''),
        );
    }

    /**
     * LA PAGE PUBLIQUE DE VERIFICATION EST ATTEIGNABLE.
     *
     * Elle ne l'etait par aucun lien : une page de verification que personne ne
     * trouve ne verifie rien. Le test suit un LIEN depuis la page d'accueil,
     * plutot que de verifier la presence d'une chaine dans un gabarit.
     */
    #[Test]
    public function la_verification_publique_est_atteignable_depuis_l_accueil(): void
    {
        $accueil = $this->get(route('home'))->assertOk();

        $this->assertStringContainsString(
            route('verify.show'),
            (string) $accueil->getContent(),
            "La page d'accueil doit mener à la vérification d'un acte."
        );

        $this->get(route('verify.show'))->assertOk();
    }

    /** Le demandeur apprend a quoi sert le code imprime sur son acte. */
    #[Test]
    public function le_demandeur_apprend_a_quoi_sert_le_code_de_son_acte(): void
    {
        $this->assertStringContainsString(
            'verification_note',
            (string) file_get_contents(resource_path('views/citizen/requests/show.blade.php')),
            "L'écran de suivi doit expliquer le code de vérification."
        );

        $this->assertNotSame(
            'citizen.tracking.verification_note',
            __('citizen.tracking.verification_note'),
            'La clé doit être traduite.'
        );
    }

    /**
     * UN DOSSIER QUI ATTEND LE DEMANDEUR LE DIT DANS LA FILE.
     *
     * Sans cela, sa duree d'attente court comme si l'agent tardait : elle
     * accuse le mauvais cote.
     */
    #[Test]
    public function la_file_distingue_un_dossier_qui_attend_le_demandeur(): void
    {
        $avant = $this->actingAs($this->officier)->get(route('officer.queue'));
        $avant->assertOk()->assertDontSee(__('officer.queue.awaiting_applicant'));

        $this->demande->complements()->create([
            'requested_by' => $this->officier->id,
            'kind' => 'id_document',
            'message' => 'Le numéro est masqué par un reflet, reprenez la photo.',
        ]);

        $this->actingAs($this->officier)
            ->get(route('officer.queue'))
            ->assertOk()
            ->assertSee(__('officer.queue.awaiting_applicant'));
    }
}
