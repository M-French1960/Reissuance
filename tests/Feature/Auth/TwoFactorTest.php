<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\CivilStatusCenter;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 2FA obligatoire pour officier, maire et administrateur (4.1 du brief).
 */
class TwoFactorTest extends TestCase
{
    private const PASSWORD = 'Corail-Tambour-97!Vertige';

    #[Test]
    #[DataProvider('rolesOfficiels')]
    public function un_role_officiel_sans_2fa_est_renvoye_vers_la_configuration(string $role): void
    {
        $center = CivilStatusCenter::factory()->create();

        $user = match ($role) {
            'officer' => User::factory()->officer($center)->create(),
            'mayor' => User::factory()->mayor($center->commune)->create(),
            default => User::factory()->admin()->create(),
        };

        // Etat reel d'un compte tout juste cree par un administrateur.
        $user->forceFill(['two_factor_confirmed_at' => null, 'status' => 'pending'])->save();

        $this->actingAs($user->refresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('two-factor.setup'));
    }

    /** @return iterable<string, array{string}> */
    public static function rolesOfficiels(): iterable
    {
        yield 'officier' => ['officer'];
        yield 'maire' => ['mayor'];
        yield 'administrateur' => ['admin'];
    }

    #[Test]
    public function la_page_de_configuration_reste_accessible_sans_2fa(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $user = User::factory()->officer($center)->create();
        $user->forceFill(['two_factor_confirmed_at' => null, 'status' => 'pending'])->save();

        $this->actingAs($user->refresh())->get(route('two-factor.setup'))->assertOk();
    }

    #[Test]
    public function un_citoyen_n_est_pas_contraint_a_la_2fa(): void
    {
        $citoyen = User::factory()->citizen()->create();

        $this->actingAs($citoyen)->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function un_compte_officiel_actif_sans_2fa_ne_peut_pas_exister_en_base(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $user = User::factory()->officer($center)->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_official_2fa_check/');

        $user->forceFill(['two_factor_confirmed_at' => null])->save();
    }

    #[Test]
    public function le_secret_2fa_n_est_jamais_serialise(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $user = User::factory()->officer($center)->create();
        $user->forceFill([
            'two_factor_secret' => 'SECRET-A-NE-PAS-EXPOSER',
            'two_factor_recovery_codes' => 'CODES-A-NE-PAS-EXPOSER',
        ])->save();

        $json = $user->refresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $json);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $json);
    }

    #[Test]
    public function le_secret_2fa_est_chiffre_en_base(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $user = User::factory()->officer($center)->create();
        $user->forceFill(['two_factor_secret' => 'SECRET-EN-CLAIR'])->save();

        $brut = DB::table('users')
            ->where('id', $user->id)->value('two_factor_secret');

        $this->assertStringNotContainsString('SECRET-EN-CLAIR', (string) $brut);
    }

    #[Test]
    public function un_utilisateur_avec_2fa_passe_par_le_defi_avant_d_etre_connecte(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $user = User::factory()->officer($center)->create(['password' => Hash::make(self::PASSWORD)]);
        $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP')])->saveQuietly();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('two-factor.login'));

        // Le mot de passe est bon, mais la session n'est PAS ouverte tant que
        // le second facteur n'a pas ete fourni.
        $this->assertGuest();
    }

    /**
     * Le parcours complet de mise en service d'un compte officiel.
     *
     * Ce test existe parce que le parcours etait bloque : un compte cree
     * desactive ne pouvait pas se connecter, donc jamais configurer sa 2FA,
     * donc jamais etre active. Le statut « pending » ouvre exactement la porte
     * necessaire, et pas davantage.
     */
    #[Test]
    public function un_compte_en_attente_accede_a_la_2fa_et_a_rien_d_autre(): void
    {
        $center = CivilStatusCenter::factory()->create();
        $user = User::factory()->officer($center)->create();
        $user->forceFill(['two_factor_confirmed_at' => null, 'status' => 'pending'])->save();

        $this->actingAs($user->refresh());

        // Autorise : la page qui lui permet de devenir utilisable.
        $this->get(route('two-factor.setup'))->assertOk();

        // Refuse : tout le reste le renvoie vers la configuration.
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));

        // Et il n'est pas deconnecte, contrairement a un compte suspendu.
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function un_compte_en_attente_peut_se_connecter_mais_pas_un_compte_desactive(): void
    {
        $center = CivilStatusCenter::factory()->create();

        $enAttente = User::factory()->officer($center)->create(['password' => Hash::make(self::PASSWORD)]);
        $enAttente->forceFill(['two_factor_confirmed_at' => null, 'status' => 'pending'])->save();

        $this->post('/login', ['email' => $enAttente->email, 'password' => self::PASSWORD]);
        $this->assertAuthenticated();
        $this->post('/logout');

        $desactive = User::factory()->officer($center)->create([
            'password' => Hash::make(self::PASSWORD),
            'status' => 'disabled',
        ]);

        $this->post('/login', ['email' => $desactive->email, 'password' => self::PASSWORD]);
        $this->assertGuest();
    }
}
