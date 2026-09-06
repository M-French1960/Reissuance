@extends('layouts.app')
@section('title', 'Confirmer votre mot de passe')

@section('content')
    <x-auth-card title="Confirmer votre mot de passe"
                 intro="Cette action est sensible : saisissez à nouveau votre mot de passe pour continuer.">
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <x-field name="password" label="Mot de passe" type="password" required autofocus
                     autocomplete="current-password" :error="$errors->first('password')" />
            <x-button type="submit" variant="primary" block>Confirmer</x-button>
        </form>
    </x-auth-card>
@endsection
