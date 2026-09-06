<?php

declare(strict_types=1);

namespace App\Http\Requests\Citizen;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Citizen;
    }

    /**
     * Validation stricte cote serveur pour TOUT champ, y compris ceux deja
     * valides cote client. La validation navigateur est de l'ergonomie, pas
     * de la securite (4.5 du brief).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'birth_place' => ['required', 'string', 'max:200'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{8,}$/'],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'birth_date.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'phone.regex' => 'Indiquez un numéro de téléphone valide, par exemple +237 6 XX XX XX XX.',
        ];
    }
}
