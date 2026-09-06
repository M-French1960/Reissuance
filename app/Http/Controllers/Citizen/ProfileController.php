<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Citizen\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Models\CitizenProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('citizen.profile', [
            'profile' => $request->user()->profile ?? new CitizenProfile,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $profile = $user->profile ?? $user->profile()->make();

            $profile->fill($data);
            $profile->completed_at = now();
            $user->profile()->save($profile);

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role->value,
                'action' => 'profile.updated',
                'auditable_type' => 'citizen_profile',
                'auditable_id' => $profile->id,
                'ip_address' => request()->ip(),
            ]);
        });

        return redirect()
            ->route('citizen.profile.edit')
            ->with('status', 'Votre profil a été enregistré.');
    }
}
