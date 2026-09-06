@extends('layouts.app')
@section('title', 'Mon profil')

@section('content')
    <h1>Mon profil</h1>
    <p>Ces informations vous identifient auprès du centre d'état civil. Elles
    sont reprises automatiquement dans vos demandes.</p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">
                {{ $errors->count() === 1 ? 'Un champ doit être corrigé' : $errors->count().' champs doivent être corrigés' }}
            </p>
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('citizen.profile.update') }}">
            @csrf @method('PATCH')

            <div class="grid grid--2">
                <x-field name="first_name" label="Prénom" required :value="$profile->first_name"
                         autocomplete="given-name" :error="$errors->first('first_name')" />
                <x-field name="last_name" label="Nom" required :value="$profile->last_name"
                         autocomplete="family-name" :error="$errors->first('last_name')" />
            </div>

            <div class="grid grid--2">
                <x-field name="birth_date" label="Date de naissance" type="date" required
                         :value="$profile->birth_date?->format('Y-m-d')" autocomplete="bday"
                         :error="$errors->first('birth_date')" />
                <x-field name="birth_place" label="Lieu de naissance" required
                         :value="$profile->birth_place" :error="$errors->first('birth_place')" />
            </div>

            <x-field name="national_id_number" label="Numéro de pièce d'identité"
                     :value="$profile->national_id_number"
                     hint="Facultatif. Ce numéro est chiffré : il n'est lisible ni dans la base, ni par l'administration du service."
                     autocomplete="off"
                     :error="$errors->first('national_id_number')" />

            <div class="grid grid--2">
                <x-field name="phone" label="Téléphone" type="tel" required :value="$profile->phone"
                         autocomplete="tel" hint="Par exemple +237 6 XX XX XX XX."
                         :error="$errors->first('phone')" />
                <x-field name="address" label="Adresse" required :value="$profile->address"
                         autocomplete="street-address" :error="$errors->first('address')" />
            </div>

            <x-button type="submit" variant="primary">Enregistrer</x-button>
        </form>
    </x-card>
@endsection
