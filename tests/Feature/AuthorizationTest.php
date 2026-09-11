<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les tests de refus de docs/PERMISSIONS.md 4.
 *
 * « Chaque Policy doit avoir un test qui vérifie le refus, pas seulement
 * l'autorisation. Un test qui ne prouve que le cas heureux ne prouve rien. »
 */
class AuthorizationTest extends TestCase
{
    private CivilStatusCenter $centerA;

    private CivilStatusCenter $centerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->centerA = CivilStatusCenter::factory()->create();
        $this->centerB = CivilStatusCenter::factory()->create();
    }

    private function requestIn(CivilStatusCenter $center, RequestStatus $status, ?User $owner = null): ReissuanceRequest
    {
        $citizen = $owner ?? User::factory()->citizen()->create();

        $request = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citizen->id,
            'civil_status_center_id' => $center->id,
            'commune_id' => $center->commune_id,
            'reason' => 'lost',
        ]);

        if ($status === RequestStatus::Draft) {
            return $request->refresh();
        }

        $request->forceFill(['submitted_at' => now()])->save();

        $officer = User::factory()->officer($center)->create();
        $mayor = User::factory()->mayor($center->commune)->create();
        $service = app(RequestTransitionService::class);

        $service->transition($request, RequestStatus::Pending, $citizen);

        if ($status !== RequestStatus::Pending) {
            $service->transition($request, RequestStatus::UnderReview, $officer);
            $request->forceFill(['assigned_officer_id' => $officer->id])->save();
        }

        if (in_array($status, [RequestStatus::AwaitingSignature, RequestStatus::Signed], true)) {
            $service->transition($request, RequestStatus::AwaitingSignature, $officer);
        }
        if ($status === RequestStatus::Signed) {
            $service->transition($request, RequestStatus::Signed, $mayor);
        }
        if ($status === RequestStatus::Escalated) {
            $service->transition($request, RequestStatus::Escalated, $officer, 'Motif de test suffisamment long.');
        }
        if ($status === RequestStatus::Rejected) {
            $service->transition($request, RequestStatus::Rejected, $officer, 'Motif de test suffisamment long.');
        }

        return $request->refresh();
    }

    /** R1 — un citoyen ne voit pas la demande d'un autre citoyen. */
    #[Test]
    public function r1_un_citoyen_ne_voit_pas_la_demande_d_un_autre(): void
    {
        $autre = $this->requestIn($this->centerA, RequestStatus::Pending);
        $citoyen = User::factory()->citizen()->create();

        $this->assertFalse($citoyen->can('view', $autre));
    }

    /** R3 — un officier ne voit pas une demande d'un autre centre. */
    #[Test]
    public function r3_un_officier_ne_voit_pas_un_autre_centre(): void
    {
        $demande = $this->requestIn($this->centerB, RequestStatus::Pending);
        $officier = User::factory()->officer($this->centerA)->create();

        $this->assertFalse($officier->can('view', $demande));
        $this->assertTrue(
            $officier->can('view', $this->requestIn($this->centerA, RequestStatus::Pending)),
            'Un officier doit voir les demandes de son propre centre.'
        );
    }

    /** R4 — un officier ne prend pas en charge une demande deja prise. */
    #[Test]
    public function r4_une_demande_deja_prise_ne_peut_pas_etre_reprise(): void
    {
        $demande = $this->requestIn($this->centerA, RequestStatus::Pending);
        $demande->forceFill(['assigned_officer_id' => User::factory()->officer($this->centerA)->create()->id])->save();

        $collegue = User::factory()->officer($this->centerA)->create();

        $this->assertFalse($collegue->can('claim', $demande->refresh()));
    }

    /** R6 — le maire ne voit pas une demande de sa commune encore en instruction. */
    #[Test]
    #[DataProvider('etatsInvisiblesAuMaire')]
    public function r6_le_maire_ne_voit_pas_les_etats_en_instruction(RequestStatus $status): void
    {
        $demande = $this->requestIn($this->centerA, $status);
        $maire = User::factory()->mayor($this->centerA->commune)->create();

        $this->assertFalse(
            $maire->can('view', $demande),
            "Le maire ne doit pas voir une demande de sa commune en {$status->value}."
        );
    }

    /** @return iterable<string, array{RequestStatus}> */
    public static function etatsInvisiblesAuMaire(): iterable
    {
        yield 'en attente de traitement' => [RequestStatus::Pending];
        yield 'en cours de verification' => [RequestStatus::UnderReview];
        yield 'signee' => [RequestStatus::Signed];
        yield 'refusee' => [RequestStatus::Rejected];
    }

    /** R7 — le maire ne voit pas une demande d'une autre commune. */
    #[Test]
    public function r7_le_maire_ne_voit_pas_une_autre_commune(): void
    {
        $demande = $this->requestIn($this->centerB, RequestStatus::AwaitingSignature);
        $maire = User::factory()->mayor($this->centerA->commune)->create();

        $this->assertFalse($maire->can('view', $demande));
    }

    /**
     * R8 et R9 — l'administrateur n'accede a AUCUN dossier.
     *
     * C'est le point le plus contre-intuitif de la matrice et le plus
     * important : un compte administrateur compromis ne doit pas ouvrir
     * l'acces aux pieces d'identite de la population.
     */
    #[Test]
    #[DataProvider('tousLesEtats')]
    public function r8_l_administrateur_ne_voit_aucune_demande(RequestStatus $status): void
    {
        $demande = $this->requestIn($this->centerA, $status);
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('view', $demande));
        $this->assertFalse($admin->can('viewIdentityDocuments', $demande));
        $this->assertFalse($admin->can('viewAny', ReissuanceRequest::class));
    }

    /** @return iterable<string, array{RequestStatus}> */
    public static function tousLesEtats(): iterable
    {
        foreach (RequestStatus::cases() as $status) {
            yield $status->value => [$status];
        }
    }

    /** R10 — l'administrateur ne signe pas. */
    #[Test]
    public function r10_l_administrateur_ne_peut_pas_signer(): void
    {
        $demande = $this->requestIn($this->centerA, RequestStatus::AwaitingSignature);

        $this->assertFalse(User::factory()->admin()->create()->can('sign', $demande));
    }

    /**
     * R13 — la portee globale tient meme sans `where` explicite.
     *
     * C'est le test le plus important du lot : il verifie que la barriere
     * fonctionne en cas d'oubli du developpeur, c'est-a-dire le scenario reel.
     */
    #[Test]
    public function r13_une_requete_sans_where_ne_fuit_pas_hors_perimetre(): void
    {
        $this->requestIn($this->centerA, RequestStatus::Pending);
        $this->requestIn($this->centerB, RequestStatus::Pending);
        $this->requestIn($this->centerB, RequestStatus::UnderReview);

        $officier = User::factory()->officer($this->centerA)->create();
        Auth::login($officier);

        // Requete deliberement naive : aucun filtre.
        $resultats = ReissuanceRequest::all();

        $this->assertGreaterThan(0, $resultats->count(), 'Le centre A doit rester visible.');
        $this->assertTrue(
            $resultats->every(fn (ReissuanceRequest $r): bool => $r->civil_status_center_id === $this->centerA->id),
            'Une requête sans where ne doit jamais remonter un autre centre.'
        );
    }

    #[Test]
    public function r13bis_le_citoyen_ne_voit_que_ses_propres_demandes_sans_where(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $this->requestIn($this->centerA, RequestStatus::Pending, $citoyen);
        $this->requestIn($this->centerA, RequestStatus::Pending);

        Auth::login($citoyen);

        $this->assertTrue(
            ReissuanceRequest::all()->every(fn (ReissuanceRequest $r): bool => $r->user_id === $citoyen->id)
        );
    }

    #[Test]
    public function r13ter_l_administrateur_ne_remonte_aucune_demande_sans_where(): void
    {
        $this->requestIn($this->centerA, RequestStatus::Pending);
        $this->requestIn($this->centerB, RequestStatus::AwaitingSignature);

        Auth::login(User::factory()->admin()->create());

        $this->assertCount(0, ReissuanceRequest::all());
    }

    /**
     * R13quater — le maire, sans `where` explicite.
     *
     * C'est le role dont la portee est la plus fine : commune ET deux etats
     * seulement. R6 et R7 la verifient par la Policy ; ce test la verifie par
     * la portee globale, qui est la barriere qui tient quand l'appelant a
     * oublie d'y penser. Sans lui, le maire etait le seul role dont la clause
     * n'etait jamais exercee a ce niveau.
     */
    #[Test]
    public function r13quater_le_maire_ne_voit_que_sa_commune_aux_deux_etats_ou_il_decide(): void
    {
        $aSigner = $this->requestIn($this->centerA, RequestStatus::AwaitingSignature);
        $escalade = $this->requestIn($this->centerA, RequestStatus::Escalated);

        // Meme commune, mais des etats ou le maire n'a pas competence.
        $this->requestIn($this->centerA, RequestStatus::Pending);
        $this->requestIn($this->centerA, RequestStatus::UnderReview);

        // Competence correcte, mais une autre commune.
        $ailleurs = $this->requestIn($this->centerB, RequestStatus::AwaitingSignature);

        Auth::login(User::factory()->mayor($this->centerA->commune)->create());

        $visibles = ReissuanceRequest::all();

        $this->assertEqualsCanonicalizing(
            [$aSigner->id, $escalade->id],
            $visibles->pluck('id')->all(),
            'Le maire ne doit voir que sa commune, et seulement en attente de signature ou escaladée.'
        );

        $this->assertNotContains(
            $ailleurs->id,
            $visibles->pluck('id')->all(),
            "Une demande d'une autre commune ne doit jamais remonter."
        );
    }

    /** Un brouillon n'existe que pour son auteur, jamais pour un officier. */
    #[Test]
    public function un_brouillon_reste_invisible_a_l_officier_du_centre(): void
    {
        $brouillon = $this->requestIn($this->centerA, RequestStatus::Draft);
        $officier = User::factory()->officer($this->centerA)->create();

        $this->assertFalse($officier->can('view', $brouillon));

        Auth::login($officier);
        $this->assertFalse(ReissuanceRequest::all()->contains('id', $brouillon->id));
    }

    /** Aucun compte ne peut etre supprime, jamais (docs/PERMISSIONS.md 3.3). */
    #[Test]
    public function aucun_role_ne_peut_supprimer_un_compte(): void
    {
        $cible = User::factory()->citizen()->create();

        foreach ([UserRole::Citizen, UserRole::Officer, UserRole::Mayor, UserRole::Admin] as $role) {
            $acteur = match ($role) {
                UserRole::Officer => User::factory()->officer($this->centerA)->create(),
                UserRole::Mayor => User::factory()->mayor($this->centerA->commune)->create(),
                UserRole::Admin => User::factory()->admin()->create(),
                UserRole::Citizen => User::factory()->citizen()->create(),
            };

            $this->assertFalse(
                $acteur->can('delete', $cible),
                "Le rôle {$role->value} ne doit pas pouvoir supprimer un compte."
            );
        }
    }

    /** Un administrateur ne peut pas se desactiver lui-meme. */
    #[Test]
    public function un_administrateur_ne_peut_pas_changer_son_propre_statut(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('changeStatus', $admin));
        $this->assertTrue($admin->can('changeStatus', User::factory()->citizen()->create()));
    }
}
