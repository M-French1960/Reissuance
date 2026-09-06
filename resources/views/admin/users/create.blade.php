@extends('layouts.app')
@section('title', 'Créer un compte')

@section('content')
    <h1>Créer un compte officiel</h1>

    <x-alert variant="attention" title="Ce que fait ce formulaire">
        Le compte est créé <strong>désactivé</strong>, sans mot de passe choisi par vous :
        son titulaire reçoit un lien pour définir le sien. Il devra configurer sa
        double authentification avant que vous puissiez l'activer.
    </x-alert>

    <x-card>
        @if ($errors->any())
            <x-alert variant="danger" title="Le compte n'a pas été créé">
                <ul class="alert__list">
                    @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <x-field name="name" label="Nom complet" required :error="$errors->first('name')" />
            <x-field name="email" label="Adresse électronique" type="email" required
                     :error="$errors->first('email')" />

            <div class="field">
                <label class="field__label" for="role">Rôle <span aria-hidden="true">*</span></label>
                <select class="field__control" id="role" name="role" required>
                    <option value="officer" @selected(old('role') === 'officer')>Officier d'état civil</option>
                    <option value="mayor" @selected(old('role') === 'mayor')>Maire</option>
                    <option value="admin" @selected(old('role') === 'admin')>Administrateur</option>
                </select>
                <span class="field__hint">Les comptes citoyens ne se créent pas ici : les citoyens s'inscrivent eux-mêmes.</span>
            </div>

            <div class="field">
                <label class="field__label" for="civil_status_center_id">Centre d'état civil (officiers)</label>
                <select class="field__control" id="civil_status_center_id" name="civil_status_center_id">
                    <option value="">—</option>
                    @foreach ($centers as $center)
                        <option value="{{ $center->id }}" @selected(old('civil_status_center_id') == $center->id)>
                            {{ $center->name }} ({{ $center->commune?->name }})
                        </option>
                    @endforeach
                </select>
                @if ($errors->has('civil_status_center_id'))
                    <p class="field__error">{{ $errors->first('civil_status_center_id') }}</p>
                @endif
            </div>

            <div class="field">
                <label class="field__label" for="commune_id">Commune (maires)</label>
                <select class="field__control" id="commune_id" name="commune_id">
                    <option value="">—</option>
                    @foreach ($communes as $commune)
                        <option value="{{ $commune->id }}" @selected(old('commune_id') == $commune->id)>{{ $commune->name }}</option>
                    @endforeach
                </select>
                @if ($errors->has('commune_id'))
                    <p class="field__error">{{ $errors->first('commune_id') }}</p>
                @endif
            </div>

            <x-button type="submit" variant="primary">Créer le compte</x-button>
            <x-button href="{{ route('admin.users.index') }}" variant="secondary">Annuler</x-button>
        </form>
    </x-card>
@endsection
