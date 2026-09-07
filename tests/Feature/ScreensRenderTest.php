<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\VerificationResult;
use App\Models\CivilStatusCenter;
use App\Models\ReissuanceRequest;
use App\Models\User;
use App\Services\RequestTransitionService;
use App\Services\VerificationWorkflow;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Chaque ecran s'affiche, avec des donnees, pour le role qui le voit.
 *
 * ViewCompilationTest prouve que les 47 vues compilent. Ce n'est pas la meme
 * chose que de les RENDRE : le tableau de bord de l'administrateur compilait
 * parfaitement et renvoyait 500 depuis le jalon 2, parce qu'il reconvertissait
 * une enumeration deja convertie. Un audit d'accessibilite au navigateur l'a
 * trouve ; aucun test ne l'avait vu, car aucun test n'ouvrait un tableau de
 * bord.
 *
 * Ce test ouvre tout ce qui s'ouvre sans parametre, pour les quatre roles, sur
 * une base qui contient de vraies lignes — un ecran vide ne prouve rien.
 */
class ScreensRenderTest extends TestCase
{
    private CivilStatusCenter $centre;

    private ReissuanceRequest $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->centre = CivilStatusCenter::factory()->create();
        $this->preparerDesDonnees();
    }

    /**
     * Ecrans sans parametre d'URL, par role.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function ecrans(): array
    {
        return [
            'citoyen' => ['citizen', [
                'dashboard', 'citizen.requests.index', 'citizen.profile.edit',
                'notifications.index', 'two-factor.setup',
            ]],
            'officier' => ['officer', [
                'dashboard', 'officer.queue', 'notifications.index', 'two-factor.setup',
            ]],
            'maire' => ['mayor', [
                'dashboard', 'mayor.dashboard', 'notifications.index', 'two-factor.setup',
            ]],
            'administrateur' => ['admin', [
                'dashboard', 'admin.users.index', 'admin.users.create',
                'admin.audit.index', 'notifications.index', 'two-factor.setup',
            ]],
        ];
    }

    /** @param list<string> $routes */
    #[Test]
    #[DataProvider('ecrans')]
    public function les_ecrans_d_un_role_s_affichent(string $role, array $routes): void
    {
        $utilisateur = match ($role) {
            'citizen' => tap(User::factory()->citizen()->create(), function (User $u): void {
                $u->profile()->create([
                    'first_name' => 'Personne', 'last_name' => 'DE TEST',
                    'national_id_number' => 'DEMO-910000001', 'completed_at' => now(),
                ]);
            }),
            'officer' => User::factory()->officer($this->centre)->create(),
            'mayor' => User::factory()->mayor($this->centre->commune)->create(),
            'admin' => User::factory()->admin()->create(),
        };

        foreach ($routes as $route) {
            $this->actingAs($utilisateur)
                ->get(route($route))
                ->assertOk("L'écran {$route} ne s'affiche pas pour le rôle {$role}.");
        }
    }

    /** Les ecrans qui prennent une demande en parametre. */
    #[Test]
    public function les_ecrans_lies_a_une_demande_s_affichent(): void
    {
        $citoyen = $this->demande->citizen;
        $officier = $this->demande->assignedOfficer;
        $maire = User::factory()->mayor($this->centre->commune)->create();

        $this->actingAs($citoyen)
            ->get(route('citizen.requests.show', $this->demande))->assertOk();

        foreach ([1, 2, 3, 4, 5] as $etape) {
            $this->actingAs($officier)
                ->get(route('officer.verification.step', [$this->demande, $etape]))
                ->assertOk("L'écran de vérification {$etape} ne s'affiche pas.");
        }

        app(RequestTransitionService::class)->transition(
            $this->demande, RequestStatus::AwaitingSignature, $officier
        );

        $this->actingAs($maire)
            ->get(route('mayor.review', $this->demande->refresh()))->assertOk();
    }

    /** Les quatre etapes de l'assistant citoyen, sur un brouillon reel. */
    #[Test]
    public function les_quatre_etapes_de_l_assistant_s_affichent(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-920000001', 'completed_at' => now(),
        ]);

        $this->actingAs($citoyen)->post(route('citizen.requests.start'));
        $brouillon = ReissuanceRequest::withoutGlobalScopes()
            ->where('user_id', $citoyen->id)->latest('id')->firstOrFail();

        $charges = [
            1 => ['reason' => 'lost', 'copies_requested' => 1],
            2 => [
                'full_name_at_birth' => 'Personne DE TEST', 'date_of_birth' => '1990-01-15',
                'place_of_birth' => 'Yaoundé', 'registration_year' => 1990,
                'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
                'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
                'parents_address' => 'Adresse de test',
            ],
            3 => ['civil_status_center_id' => $this->centre->id],
        ];

        foreach ([1, 2, 3, 4] as $etape) {
            $this->actingAs($citoyen)
                ->get(route('citizen.requests.step', [$brouillon, $etape]))
                ->assertOk("L'étape {$etape} de l'assistant ne s'affiche pas.");

            if (isset($charges[$etape])) {
                $this->actingAs($citoyen)
                    ->post(route('citizen.requests.save', [$brouillon, $etape]), $charges[$etape])
                    ->assertSessionHasNoErrors();
            }
        }
    }

    /**
     * Des lignes en base pour chaque table que les ecrans parcourent : un
     * tableau vide ne leve aucune des erreurs qu'on cherche ici.
     */
    private function preparerDesDonnees(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $citoyen->profile()->create([
            'first_name' => 'Personne', 'last_name' => 'DE TEST',
            'national_id_number' => 'DEMO-930000001', 'completed_at' => now(),
        ]);
        $officier = User::factory()->officer($this->centre)->create();

        $this->demande = ReissuanceRequest::withoutGlobalScopes()->create([
            'reference' => ReissuanceRequest::generateReference(),
            'user_id' => $citoyen->id,
            'civil_status_center_id' => $this->centre->id,
            'commune_id' => $this->centre->commune_id,
            'reason' => 'lost',
            'full_name_at_birth' => 'Personne DE TEST',
            'date_of_birth' => '1990-01-15',
            'place_of_birth' => 'Yaoundé',
            'registration_year' => 1990,
            'father_name' => 'Père', 'father_nationality' => 'Camerounaise',
            'mother_name' => 'Mère', 'mother_nationality' => 'Camerounaise',
            'parents_address' => 'Adresse de test',
        ]);
        $this->demande->forceFill(['submitted_at' => now()])->save();

        $transitions = app(RequestTransitionService::class);
        $transitions->transition($this->demande, RequestStatus::Pending, $citoyen);
        $transitions->transition($this->demande, RequestStatus::UnderReview, $officier);
        $this->demande->forceFill(['assigned_officer_id' => $officier->id])->save();
        $this->demande->refresh();

        $workflow = app(VerificationWorkflow::class);
        foreach (VerificationWorkflow::VERIFICATION_STEPS as $n) {
            $workflow->record($this->demande, $n, $officier, VerificationResult::Match);
        }
        $this->demande->refresh();
    }
}
