@extends('layouts.app')
@section('title', "Poste de l'officier")

@section('content')
    <h1>Poste de vérification</h1>
    <p>Centre de rattachement : <strong>{{ auth()->user()->center?->name ?? '—' }}</strong>.
    Vous ne voyez que les demandes de ce centre.</p>

    <div class="grid grid--3">
        @foreach ([\App\Enums\RequestStatus::Pending, \App\Enums\RequestStatus::UnderReview, \App\Enums\RequestStatus::AwaitingSignature] as $status)
            <x-card>
                <p class="stat__label">{{ $status->label() }}</p>
                <p class="stat__value">{{ $counts[$status->value] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-alert variant="attention" title="Jalon 2">
        La file de traitement et la vérification en 5 étapes arrivent au jalon 4.
    </x-alert>
@endsection
