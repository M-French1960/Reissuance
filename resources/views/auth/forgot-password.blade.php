@extends('layouts.app')
@section('title', 'Mot de passe oublié')

@section('content')
    <x-auth-card title="Mot de passe oublié"
                 intro="Indiquez votre adresse : si un compte y est associé, vous recevrez un lien de réinitialisation.">
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <x-field name="email" label="Adresse électronique" type="email" required autofocus
                     autocomplete="username" :error="$errors->first('email')" />
            <x-button type="submit" variant="primary" block>Envoyer le lien</x-button>
        </form>

        <p class="auth-card__links"><a href="{{ route('login') }}">Retour à la connexion</a></p>
    </x-auth-card>
@endsection
