@extends('citizen.wizard._layout')

@section('wizard')
    <x-card title="Détails de l'acte de naissance">
        <p>Ces informations permettent de retrouver votre acte d'origine dans le
        registre. Indiquez-les telles qu'elles y figurent, même si elles diffèrent
        de votre usage actuel.</p>

        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]) }}">
            @csrf

            <x-field name="full_name_at_birth" label="Nom complet à la naissance" required
                     :value="$draft->full_name_at_birth"
                     hint="Y compris les prénoms intermédiaires."
                     :error="$errors->first('full_name_at_birth')" />

            <div class="grid grid--2">
                <x-field name="date_of_birth" label="Date de naissance" type="date" required
                         :value="$draft->date_of_birth?->format('Y-m-d')"
                         :error="$errors->first('date_of_birth')" />
                <x-field name="registration_year" label="Année d'enregistrement" type="number" required
                         :value="$draft->registration_year" min="1900" max="{{ date('Y') }}"
                         hint="Quatre chiffres, par exemple 1990."
                         :error="$errors->first('registration_year')" />
            </div>

            <x-field name="place_of_birth" label="Lieu de naissance" required
                     :value="$draft->place_of_birth"
                     hint="Ville, et hôpital si vous le connaissez."
                     :error="$errors->first('place_of_birth')" />

            <x-field name="original_certificate_number" label="Numéro de l'acte d'origine"
                     :value="$draft->original_certificate_number"
                     hint="Facultatif. Si vous le connaissez, la recherche sera plus rapide."
                     :error="$errors->first('original_certificate_number')" />

            <h3 class="divider-title">Vos parents</h3>

            <div class="grid grid--2">
                <x-field name="father_name" label="Nom complet du père" required
                         :value="$draft->father_name" :error="$errors->first('father_name')" />
                <x-field name="mother_name" label="Nom complet de la mère" required
                         :value="$draft->mother_name" :error="$errors->first('mother_name')" />
            </div>

            <div class="grid grid--2">
                <x-field name="father_nationality" label="Nationalité du père" required
                         :value="$draft->father_nationality ?? 'Camerounaise'"
                         :error="$errors->first('father_nationality')" />
                <x-field name="mother_nationality" label="Nationalité de la mère" required
                         :value="$draft->mother_nationality ?? 'Camerounaise'"
                         :error="$errors->first('mother_nationality')" />
            </div>

            <x-field name="parents_address" label="Adresse des parents au moment de la naissance" required
                     :value="$draft->parents_address" :error="$errors->first('parents_address')" />

            <div class="actions">
                <x-button href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 1]) }}" variant="secondary">Retour</x-button>
                <x-button type="submit" variant="primary">Continuer</x-button>
            </div>
        </form>
    </x-card>
@endsection
