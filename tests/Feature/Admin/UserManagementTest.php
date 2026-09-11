<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CivilStatusCenter;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    #[Test]
    public function un_administrateur_cree_un_officier_en_attente_et_sans_mot_de_passe_choisi(): void
    {
        Notification::fake();
        $center = CivilStatusCenter::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Officier DE TEST',
            'email' => 'nouvel-officier@example.test',
            'role' => 'officer',
            'civil_status_center_id' => $center->id,
        ])->assertRedirect(route('admin.users.index'));

        $officier = User::where('email', 'nouvel-officier@example.test')->firstOrFail();

        $this->assertSame(UserRole::Officer, $officier->role);
        $this->assertSame($center->id, $officier->civil_status_center_id);
        // Cree « pending » : il peut se connecter pour poser sa 2FA, et rien
        // d'autre. Un compte « disabled » ne le pourrait pas, ce qui rendrait
        // son activation impossible.
        $this->assertSame('pending', $officier->status);
        $this->assertNull($officier->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $officier->id,
            'action' => 'account.created',
        ]);
    }

    #[Test]
    public function un_officier_sans_centre_est_refuse_avec_un_message_utilisable(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Officier DE TEST',
            'email' => 'sans-centre@example.test',
            'role' => 'officer',
        ])->assertSessionHasErrors('civil_status_center_id');

        $this->assertDatabaseMissing('users', ['email' => 'sans-centre@example.test']);
    }

    #[Test]
    public function un_maire_sans_commune_est_refuse(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Maire DE TEST',
            'email' => 'sans-commune@example.test',
            'role' => 'mayor',
        ])->assertSessionHasErrors('commune_id');
    }

    /** L'administrateur ne cree pas de citoyens : ils s'inscrivent eux-memes. */
    #[Test]
    public function un_administrateur_ne_peut_pas_creer_un_compte_citoyen(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Citoyen DE TEST',
            'email' => 'citoyen-cree@example.test',
            'role' => 'citizen',
        ])->assertSessionHasErrors('role');
    }

    #[Test]
    public function activer_un_compte_officiel_sans_2fa_est_refuse(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($center)->create([
            'status' => 'disabled',
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.users.status', $officier), [
                'status' => 'active',
                'reason' => 'Prise de fonction au centre.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('disabled', $officier->refresh()->status);
    }

    #[Test]
    public function un_changement_de_statut_exige_un_motif_et_le_journalise(): void
    {
        $citoyen = User::factory()->citizen()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $citoyen), ['status' => 'suspended'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $citoyen), [
                'status' => 'suspended',
                'reason' => 'Demande frauduleuse constatée le 6 septembre.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('suspended', $citoyen->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_id' => $citoyen->id,
            'action' => 'account.status_changed',
            'from_status' => 'active',
            'to_status' => 'suspended',
        ]);
    }

    /**
     * R8 et R9 au niveau des routes : aucune route du portail administrateur
     * n'expose de contenu de dossier, et aucun autre role n'y accede.
     */
    #[Test]
    #[DataProvider('rolesNonAdministrateurs')]
    public function seul_l_administrateur_accede_au_portail(string $role): void
    {
        $center = CivilStatusCenter::factory()->create();

        $user = match ($role) {
            'officer' => User::factory()->officer($center)->create(),
            'mayor' => User::factory()->mayor($center->commune)->create(),
            default => User::factory()->citizen()->create(),
        };

        foreach ([route('admin.users.index'), route('admin.users.create'), route('admin.audit.index')] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function rolesNonAdministrateurs(): iterable
    {
        yield 'citoyen' => ['citizen'];
        yield 'officier' => ['officer'];
        yield 'maire' => ['mayor'];
    }

    #[Test]
    public function un_visiteur_non_connecte_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function le_journal_d_audit_n_expose_que_des_metadonnees(): void
    {
        $admin = $this->admin();
        $citoyen = User::factory()->citizen()->create();

        AuditLog::create([
            'actor_id' => $citoyen->id,
            'actor_role' => 'citizen',
            'action' => 'identity.viewed',
            'auditable_type' => 'request_attachment',
            'auditable_id' => 4242,
            'reason' => 'CONTENU-SENSIBLE-A-NE-PAS-AFFICHER',
        ]);

        $reponse = $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk();

        $reponse->assertSee('identity.viewed');
        // Le motif peut contenir du contenu de dossier : il n'est pas exposé.
        $reponse->assertDontSee('CONTENU-SENSIBLE-A-NE-PAS-AFFICHER');
    }

    /**
     * Le rattachement d'un agent — le cas « Manage Accounts » du diagramme.
     *
     * CES TESTS MANQUAIENT. `UseCaseCoverageTest` l'a revele : la route
     * `admin.users.reassign` n'etait exercee par aucun test, alors meme que
     * ses regles de validation et sa Policy venaient d'etre corrigees en D-058
     * — un rattachement mal forme rendait 500. Corriger sans test, c'est
     * corriger jusqu'a la prochaine fois.
     */
    #[Test]
    public function un_officier_change_de_centre(): void
    {
        $depart = CivilStatusCenter::factory()->create();
        $arrivee = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($depart)->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.users.reassign', $officier), [
                'civil_status_center_id' => $arrivee->id,
                'reason' => 'Mutation demandée par le centre de départ.',
            ])
            ->assertRedirect();

        $this->assertSame($arrivee->id, $officier->fresh()->civil_status_center_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.reassigned',
            'auditable_type' => 'user',
            'auditable_id' => $officier->id,
        ]);
    }

    #[Test]
    public function un_maire_change_de_commune(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $ailleurs = CivilStatusCenter::factory()->create();
        $maire = User::factory()->mayor($centre->commune)->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.users.reassign', $maire), [
                'commune_id' => $ailleurs->commune_id,
                'reason' => 'Changement de commune après élection.',
            ])
            ->assertRedirect();

        $this->assertSame($ailleurs->commune_id, $maire->fresh()->commune_id);
    }

    /**
     * Un rattachement absent est une erreur de validation, pas une erreur 500.
     *
     * `commune_id` etait `nullable` : la colonne partait a NULL,
     * `users_role_scope_check` refusait, et l'utilisateur recevait une page
     * d'erreur au lieu d'un message (D-058).
     */
    #[Test]
    public function un_maire_ne_peut_pas_etre_laisse_sans_commune(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $maire = User::factory()->mayor($centre->commune)->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.users.reassign', $maire), [
                'reason' => 'Tentative de rattachement sans commune.',
            ])
            ->assertSessionHasErrors('commune_id');

        $this->assertNotNull($maire->fresh()->commune_id);
    }

    #[Test]
    public function un_officier_ne_peut_pas_etre_laisse_sans_centre(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($centre)->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.users.reassign', $officier), [
                'reason' => 'Tentative de rattachement sans centre.',
            ])
            ->assertSessionHasErrors('civil_status_center_id');

        $this->assertNotNull($officier->fresh()->civil_status_center_id);
    }

    /**
     * Un administrateur n'a PAS de rattachement, et ne peut pas en recevoir.
     *
     * `isOfficial()` n'exclut que le citoyen, si bien que la Policy admettait
     * l'administrateur ; `users_role_scope_check` refusait ensuite, en 500.
     */
    #[Test]
    public function un_administrateur_ne_peut_pas_etre_rattache(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $cible = User::factory()->admin()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.users.reassign', $cible), [
                'commune_id' => $centre->commune_id,
                'reason' => "Tentative de rattachement d'un administrateur.",
            ])
            ->assertForbidden();

        $this->assertNull($cible->fresh()->commune_id);
    }

    #[Test]
    public function un_citoyen_ne_peut_pas_etre_rattache(): void
    {
        $centre = CivilStatusCenter::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.users.reassign', User::factory()->citizen()->create()), [
                'civil_status_center_id' => $centre->id,
                'reason' => "Tentative de rattachement d'un citoyen.",
            ])
            ->assertForbidden();
    }

    #[Test]
    public function seul_un_administrateur_peut_rattacher(): void
    {
        $centre = CivilStatusCenter::factory()->create();
        $officier = User::factory()->officer($centre)->create();

        $this->actingAs($officier)
            ->patch(route('admin.users.reassign', $officier), [
                'civil_status_center_id' => $centre->id,
                'reason' => 'Un agent ne se rattache pas lui-même.',
            ])
            ->assertForbidden();
    }
}
