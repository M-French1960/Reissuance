<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    /** @param  array<string, mixed>  $input */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => PasswordPolicy::rules()])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
        ])->save();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_role' => $user->role->value,
            'action' => 'account.password_reset',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'ip_address' => request()->ip(),
        ]);
    }
}
