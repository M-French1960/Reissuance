<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\CivilStatusCenter;
use App\Models\Commune;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * Aucune donnee reelle, meme en test (garde-fou n1) : le domaine
     * .test est reserve par la RFC 2606 et ne peut resoudre nulle part.
     */
    public function definition(): array
    {
        return [
            'name' => 'Personne DE TEST',
            'email' => 'test-'.Str::lower(Str::random(12)).'@example.test',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('mot-de-passe-de-test'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Citizen->value,
            'status' => 'active',
        ];
    }

    public function citizen(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Citizen->value]);
    }

    /**
     * Le secret TOTP d'un compte officiel de test.
     *
     * POSE PAR LES FABRIQUES DEPUIS D-069. Elles marquaient auparavant la 2FA
     * « confirmee » sans secret — un etat que la contrainte en base acceptait
     * alors, et que toute la suite tenait pour normal. Ce n'est plus le cas :
     * une 2FA confirmee sans secret est refusee par MySQL, et un maire sans
     * secret ne peut pas signer.
     *
     * Chiffre comme Fortify le chiffre, parce que le cast `encrypted` du
     * modele ajoute sa propre couche par-dessus.
     */
    private static function secretDeuxFacteurs(): string
    {
        return Fortify::currentEncrypter()->encrypt(
            app(Google2FA::class)->generateSecretKey()
        );
    }

    /**
     * Un officier a toujours un centre : la contrainte users_role_scope_check
     * refuse toute autre combinaison.
     */
    public function officer(?CivilStatusCenter $center = null): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Officer->value,
            'civil_status_center_id' => $center?->id ?? CivilStatusCenter::factory(),
            'commune_id' => null,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => self::secretDeuxFacteurs(),
        ]);
    }

    public function mayor(?Commune $commune = null): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Mayor->value,
            'commune_id' => $commune?->id ?? Commune::factory(),
            'civil_status_center_id' => null,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => self::secretDeuxFacteurs(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Admin->value,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => self::secretDeuxFacteurs(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }
}
