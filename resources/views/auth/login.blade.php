@extends('layouts.app')
@section('title', 'Connexion')

@section('content')
    <x-auth-card title="Connexion" intro="Accédez à votre espace pour suivre ou déposer une demande.">
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <x-field name="email" label="Adresse électronique" type="email"
                     autocomplete="username" required autofocus
                     :error="$errors->first('email')" />

            <x-field name="password" label="Mot de passe" type="password"
                     autocomplete="current-password" required
                     :error="$errors->first('password')" />

            <div class="field field--inline">
                <input type="checkbox" id="remember" name="remember" class="field__checkbox">
                <label for="remember">Rester connecté sur cet appareil</label>
            </div>

            <x-button type="submit" variant="primary" block>Se connecter</x-button>
        </form>

        <p class="auth-card__links">
            <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('register') }}">Créer un compte citoyen</a>
        </p>
    </x-auth-card>
@endsection
