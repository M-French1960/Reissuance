<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private const PASSWORD = 'Corail-Tambour-97!Vertige';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    private function citizen(string $status = 'active'): User
    {
        return User::factory()->citizen()->create([
            'password' => Hash::make(self::PASSWORD),
            'status' => $status,
        ]);
    }

    #[Test]
    public function un_citoyen_actif_peut_se_connecter(): void
    {
        $user = $this->citizen();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect('/tableau-de-bord');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function un_mot_de_passe_errone_est_refuse_et_journalise(): void
    {
        $user = $this->citizen();

        $this->post('/login', ['email' => $user->email, 'password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors();

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'action' => 'auth.failed',
        ]);
    }

    /**
     * Test R14 de docs/PERMISSIONS.md.
     *
     * Un compte suspendu ou desactive ne doit pas pouvoir ouvrir de session,
     * meme avec le bon mot de passe.
     */
    #[Test]
    #[DataProvider('statutsInactifs')]
    public function un_compte_inactif_ne_peut_pas_se_connecter(string $statut): void
    {
        $user = $this->citizen($statut);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors();

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'action' => 'auth.refused_inactive_account',
        ]);
    }

    /** @return iterable<string, array{string}> */
    public static function statutsInactifs(): iterable
    {
        yield 'suspendu' => ['suspended'];
        yield 'desactive' => ['disabled'];
    }

    /**
     * Test R14, second volet : une session deja ouverte doit tomber des que
     * le compte cesse d'etre actif. Sans cela, une suspension ne prendrait
     * effet qu'a la prochaine connexion.
     */
    #[Test]
    public function une_session_ouverte_tombe_quand_le_compte_est_suspendu(): void
    {
        $user = $this->citizen();
        $this->actingAs($user);

        $this->get('/tableau-de-bord')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get('/tableau-de-bord')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function le_message_d_erreur_ne_revele_pas_l_existence_du_compte(): void
    {
        $existant = $this->citizen('suspended');

        $messageCompteSuspendu = $this->post('/login', [
            'email' => $existant->email, 'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email')->getSession()->get('errors')->getBag('default')->first('email');

        $this->flushSession();
        RateLimiter::clear('login');

        $messageCompteInconnu = $this->post('/login', [
            'email' => 'inconnu-'.uniqid().'@example.test', 'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email')->getSession()->get('errors')->getBag('default')->first('email');

        $this->assertNotEmpty($messageCompteSuspendu);
        $this->assertSame(
            $messageCompteSuspendu,
            $messageCompteInconnu,
            "Le message doit être identique : le distinguer révélerait qu'un compte existe."
        );
    }

    /** 4.1 du brief : limitation de debit sur la connexion. */
    #[Test]
    public function les_tentatives_repetees_sont_bridees(): void
    {
        $user = $this->citizen();
        $max = (int) config('phoenix.security.login_max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'faux']);
        }

        // Le bridage renvoie un 429, pas une redirection : la tentative
        // n'atteint jamais la verification du mot de passe.
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(429);

        $this->assertGuest();
    }

    #[Test]
    public function la_deconnexion_invalide_la_session(): void
    {
        $user = $this->citizen();
        $this->actingAs($user);

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }
}
