<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Cree un compte CITOYEN, et rien d'autre.
     *
     * Le role n'est jamais lu depuis la requete : il est ecrit en dur. Le 4.1
     * du brief est categorique — un officier ou un maire n'existe que cree par
     * un administrateur. Test de refus R11 de docs/PERMISSIONS.md.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(),
            'accepts_terms' => ['accepted'],
        ], [
            'accepts_terms.accepted' => "Vous devez accepter les conditions d'utilisation pour créer un compte.",
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => trim($input['first_name'].' '.$input['last_name']),
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                // Ecrit en dur. Toute valeur « role » presente dans $input est
                // purement et simplement ignoree.
                'role' => UserRole::Citizen->value,
                'status' => 'active',
            ]);

            $user->forceFill(['password_changed_at' => now()])->save();

            $user->profile()->create([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
            ]);

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => UserRole::Citizen->value,
                'action' => 'account.registered',
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'ip_address' => request()->ip(),
            ]);

            return $user;
        });
    }
}
