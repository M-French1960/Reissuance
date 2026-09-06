@extends('layouts.app')
@section('title', 'Créer un compte')

@section('content')
    <x-auth-card title="Créer un compte citoyen"
                 intro="Ce formulaire crée uniquement un compte citoyen. Les comptes d'officier et de maire sont créés par l'administration.">
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="grid grid--2">
                <x-field name="first_name" label="Prénom" required autocomplete="given-name"
                         :error="$errors->first('first_name')" />
                <x-field name="last_name" label="Nom" required autocomplete="family-name"
                         :error="$errors->first('last_name')" />
            </div>

            <x-field name="email" label="Adresse électronique" type="email" required
                     autocomplete="username" :error="$errors->first('email')" />

            <x-field name="password" label="Mot de passe" type="password" required
                     autocomplete="new-password"
                     hint="Au moins 12 caractères, avec majuscules, minuscules, chiffres et symboles. Une suite de mots sans rapport avec vous est plus sûre qu'un mot compliqué."
                     :error="$errors->first('password')" />

            <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password"
                     required autocomplete="new-password" />

            <div class="field field--inline">
                <input type="checkbox" id="accepts_terms" name="accepts_terms" value="1"
                       class="field__checkbox" required
                       @if ($errors->has('accepts_terms')) aria-invalid="true" aria-describedby="accepts_terms-error" @endif>
                <label for="accepts_terms">
                    J'accepte que mes données soient traitées pour instruire ma demande.
                </label>
            </div>
            @if ($errors->has('accepts_terms'))
                <p class="field__error" id="accepts_terms-error">{{ $errors->first('accepts_terms') }}</p>
            @endif

            <x-button type="submit" variant="primary" block>Créer mon compte</x-button>
        </form>

        <p class="auth-card__links">
            <a href="{{ route('login') }}">J'ai déjà un compte</a>
        </p>
    </x-auth-card>
@endsection
