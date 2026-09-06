<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfficialUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Un administrateur ne cree que des roles officiels : il ne cree
            // pas de citoyens, ceux-ci s'inscrivent eux-memes.
            'role' => ['required', Rule::in([
                UserRole::Officer->value,
                UserRole::Mayor->value,
                UserRole::Admin->value,
            ])],
            'civil_status_center_id' => ['nullable', 'integer', 'exists:civil_status_centers,id'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
        ];
    }

    /**
     * Le rattachement depend du role.
     *
     * La contrainte users_role_scope_check l'impose deja en base ; on le
     * verifie ici pour rendre un message utilisable plutot qu'une erreur SQL.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $role = $this->input('role');

                if ($role === UserRole::Officer->value && ! $this->filled('civil_status_center_id')) {
                    $validator->errors()->add(
                        'civil_status_center_id',
                        "Un officier doit être rattaché à un centre d'état civil."
                    );
                }

                if ($role === UserRole::Mayor->value && ! $this->filled('commune_id')) {
                    $validator->errors()->add(
                        'commune_id',
                        'Un maire doit être rattaché à une commune.'
                    );
                }
            },
        ];
    }
}
