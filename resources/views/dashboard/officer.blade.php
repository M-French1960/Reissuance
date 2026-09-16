@extends('layouts.app')
@section('title', "Poste de l'officier")

@section('content')
    {{--
        L'ECRAN QUI SUIT LA CONNEXION D'UN OFFICIER (D-073).

        Il est reste une ebauche du jalon 2 : trois compteurs, puis « Jalon 2 —
        La file de traitement et la vérification en 5 étapes arrivent au jalon
        4 ». Les deux existent depuis longtemps. Et aucun des compteurs ne
        menait a la file : l'agent lisait un chiffre sans pouvoir l'ouvrir.
    --}}
    <h1>Poste de vérification</h1>
    <p>Centre de rattachement : <strong>{{ auth()->user()->center?->name ?? '—' }}</strong>.
    Vous ne voyez que les demandes de ce centre.</p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    {{-- Chaque compteur OUVRE la file filtree sur son statut. --}}
    <div class="grid grid--3">
        @foreach ([\App\Enums\RequestStatus::Pending, \App\Enums\RequestStatus::UnderReview, \App\Enums\RequestStatus::AwaitingSignature] as $status)
            <x-card>
                <p class="stat__label">{{ $status->label() }}</p>
                <p class="stat__value">{{ $counts[$status->value] }}</p>
                {{-- Le filtre s'appelle `statut` cote controleur, pas `status`. --}}
                <x-button href="{{ route('officer.queue', ['statut' => $status->value]) }}" variant="secondary">
                    Ouvrir
                </x-button>
            </x-card>
        @endforeach
    </div>

    <x-card title="Votre file de traitement">
        <p>
            Prenez un dossier en charge, puis conduisez les cinq étapes de
            vérification&nbsp;: informations, pièce d'identité, photographies,
            registre d'état civil, puis votre décision.
        </p>
        <div class="row-actions">
            <x-button href="{{ route('officer.queue') }}" variant="primary">Ouvrir la file</x-button>
            <x-button href="{{ route('notifications.index') }}" variant="secondary">Mes notifications</x-button>
        </div>
    </x-card>
@endsection
