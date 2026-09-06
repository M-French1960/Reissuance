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
        ]);
    }

    public function mayor(?Commune $commune = null): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Mayor->value,
            'commune_id' => $commune?->id ?? Commune::factory(),
            'civil_status_center_id' => null,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Admin->value,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }
}
