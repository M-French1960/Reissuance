@extends('layouts.app')
@section('title', 'Nouveau mot de passe')

@section('content')
    <x-auth-card title="Choisir un nouveau mot de passe">
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-field name="email" label="Adresse électronique" type="email" required
                     :value="$request->email" autocomplete="username"
                     :error="$errors->first('email')" />

            <x-field name="password" label="Nouveau mot de passe" type="password" required
                     autocomplete="new-password"
                     hint="Au moins 12 caractères, avec majuscules, minuscules, chiffres et symboles."
                     :error="$errors->first('password')" />

            <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password"
                     required autocomplete="new-password" />

            <x-button type="submit" variant="primary" block>Enregistrer</x-button>
        </form>
    </x-auth-card>
@endsection
