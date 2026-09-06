@extends('layouts.app')
@section('title', 'Accueil')

@section('content')
    <h1>Réédition d'actes d'état civil</h1>
    <p>Plateforme de demande de réédition d'un acte de naissance perdu ou détérioré.</p>

    <x-alert variant="attention" title="Socle technique — jalon 1">
        L'authentification et les parcours métier arrivent aux jalons suivants.
        Cette page confirme que le socle fonctionne.
    </x-alert>

    <div class="grid grid--2">
        <x-card title="État du service">
            <p>Vérifie la base, le déclencheur de machine à états et l'inaltérabilité du journal d'audit.</p>
            <x-button href="{{ route('health') }}" variant="secondary">Consulter</x-button>
        </x-card>

        @if (Route::has('dev.ui'))
            <x-card title="Galerie de composants">
                <p>Tous les composants d'interface, dans tous leurs états.</p>
                <x-button href="{{ route('dev.ui') }}" variant="secondary">Ouvrir la galerie</x-button>
            </x-card>
        @endif
    </div>
@endsection
