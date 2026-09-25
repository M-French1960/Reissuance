<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CivilStatusCenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Raccorder un centre, ou corriger son raccordement.
 *
 * Le meme objet sert la creation et la mise a jour : les regles ne different
 * que par l'unicite du code, qui doit s'ignorer elle-meme sur le centre en
 * cours d'edition.
 */
class SaveCivilStatusCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $centre = $this->route('center');

        return $centre instanceof CivilStatusCenter
            ? ($this->user()?->can('update', $centre) ?? false)
            : ($this->user()?->can('create', CivilStatusCenter::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $centre = $this->route('center');

        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'commune_id' => ['required', 'integer', 'exists:communes,id'],
            /*
             * Le code porte l'index unique de la table. Le valider ici rend un
             * message utilisable plutot qu'une violation SQL, et la casse est
             * normalisee avant la comparaison : « yde1 » et « YDE1 » sont le
             * meme code, et deux centres ne peuvent pas les porter tous deux.
             */
            'code' => [
                'required', 'string', 'min:2', 'max:32', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('civil_status_centers', 'code')
                    ->ignore($centre instanceof CivilStatusCenter ? $centre->id : null),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }

        // Une case non cochee n'est pas envoyee : sans cela, decocher
        // « raccorde » ne ferait rien du tout.
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => __('admin.centers.code_format'),
            'code.unique' => __('admin.centers.code_taken'),
        ];
    }
}
