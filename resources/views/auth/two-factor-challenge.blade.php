@extends('layouts.app')
@section('title', 'Double authentification')

@section('content')
    <x-auth-card title="Double authentification"
                 intro="Saisissez le code affiché par votre application d'authentification.">
        <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf
            <x-field name="code" label="Code à 6 chiffres" required autofocus
                     autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*"
                     :error="$errors->first('code')" />
            <x-button type="submit" variant="primary" block>Valider</x-button>
        </form>

        <hr class="review-divider">

        <details>
            <summary>Je n'ai pas accès à mon application</summary>
            <p class="field__hint">Utilisez l'un des codes de secours notés lors de la configuration. Chaque code ne sert qu'une fois.</p>
            <form method="POST" action="{{ route('two-factor.login') }}">
                @csrf
                <x-field name="recovery_code" label="Code de secours"
                         autocomplete="one-time-code" :error="$errors->first('recovery_code')" />
                <x-button type="submit" variant="secondary" block>Utiliser ce code</x-button>
            </form>
        </details>
    </x-auth-card>
@endsection
