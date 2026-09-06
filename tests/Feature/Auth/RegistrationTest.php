<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    private const VALID_PASSWORD = 'Corail-Tambour-97!Vertige';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Personne',
            'last_name' => 'DE TEST',
            'email' => 'nouveau-'.uniqid().'@example.test',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'accepts_terms' => '1',
        ], $overrides);
    }

    #[Test]
    public function un_citoyen_peut_creer_un_compte(): void
    {
        $payload = $this->payload();

        $this->post('/register', $payload)->assertRedirect();

        $user = User::where('email', $payload['email'])->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Citizen, $user->role);
        $this->assertNotNull($user->profile);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'action' => 'account.registered',
        ]);
    }

    /**
     * Test R11 de docs/PERMISSIONS.md.
     *
     * Le role n'est jamais lu depuis la requete. Forcer role=officer doit
     * produire un compte citoyen — et pas une erreur, qui laisserait croire
     * que le champ est pris en compte.
     */
    #[Test]
    #[DataProvider('rolesUsurpes')]
    public function le_role_envoye_dans_la_requete_est_ignore(string $roleUsurpe): void
    {
        $payload = $this->payload(['role' => $roleUsurpe, 'status' => 'active']);

        $this->post('/register', $payload);

        $user = User::where('email', $payload['email'])->firstOrFail();

        $this->assertSame(
            UserRole::Citizen,
            $user->role,
            "Une inscription forçant role={$roleUsurpe} doit rester un compte citoyen."
        );
        $this->assertNull($user->civil_status_center_id);
        $this->assertNull($user->commune_id);
    }

    /** @return iterable<string, array{string}> */
    public static function rolesUsurpes(): iterable
    {
        yield 'officier' => ['officer'];
        yield 'maire' => ['mayor'];
        yield 'administrateur' => ['admin'];
    }

    #[Test]
    public function un_mot_de_passe_trop_court_est_refuse(): void
    {
        $this->post('/register', $this->payload([
            'password' => 'Court1!',
            'password_confirmation' => 'Court1!',
        ]))->assertSessionHasErrors('password');
    }

    /**
     * Le filet hors ligne : uncompromised() laisse passer quand l'API de
     * fuites est injoignable, ce qui est le cas par defaut en local.
     */
    #[Test]
    #[DataProvider('motsDePasseFaibles')]
    public function un_mot_de_passe_evident_est_refuse_meme_hors_ligne(string $password): void
    {
        config(['phoenix.security.check_compromised_passwords' => false]);

        $this->post('/register', $this->payload([
            'password' => $password,
            'password_confirmation' => $password,
        ]))->assertSessionHasErrors('password');
    }

    /** @return iterable<string, array{string}> */
    public static function motsDePasseFaibles(): iterable
    {
        yield 'terme du service' => ['Phoenix-EtatCivil-2026!'];
        yield 'substitution evidente' => ['P@ssw0rd-Tres-Long-99!'];
        yield 'suite croissante' => ['Abcdefghijk-12!'];
        yield 'reference geographique' => ['Yaounde-Cameroun-2026!'];
    }

    #[Test]
    public function le_consentement_est_obligatoire(): void
    {
        $payload = $this->payload();
        unset($payload['accepts_terms']);

        $this->post('/register', $payload)->assertSessionHasErrors('accepts_terms');
    }

    #[Test]
    public function le_mot_de_passe_est_hache_avec_argon2id(): void
    {
        $payload = $this->payload();
        $this->post('/register', $payload);

        $hash = User::where('email', $payload['email'])->value('password');

        $this->assertStringStartsWith('$argon2id$', $hash);
    }
}
