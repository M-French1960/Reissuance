@extends('layouts.app')
@section('title', $steps[$step])

@section('content')
    <h1>Demande de réédition</h1>
    <p class="field__hint">Référence : {{ $draft->reference }} — brouillon enregistré à chaque étape.</p>

    <x-step-indicator :steps="$steps" :current="$step" />

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">
                {{ $errors->count() === 1 ? 'Un champ doit être corrigé' : $errors->count().' champs doivent être corrigés' }}
            </p>
            <ul class="alert__list">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @yield('wizard')
@endsection
