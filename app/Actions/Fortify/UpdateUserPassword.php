<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    /** @param  array<string, mixed>  $input */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => PasswordPolicy::rules(),
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
        ])->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role->value,
            'action' => 'account.password_changed',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);
    }
}
