@extends('layouts.app')
@section('title', 'Espace du maire')

@section('content')
    <h1>Signature des actes</h1>
    <p>Commune : <strong>{{ auth()->user()->commune?->name ?? '—' }}</strong>.
    Vous ne voyez que les demandes prêtes à signer ou escaladées.</p>

    <div class="grid grid--2">
        @foreach ([\App\Enums\RequestStatus::AwaitingSignature, \App\Enums\RequestStatus::Escalated] as $status)
            <x-card>
                <p class="stat__label">{{ $status->label() }}</p>
                <p class="stat__value">{{ $counts[$status->value] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-alert variant="attention" title="Jalon 2">
        Les deux files et la signature électronique arrivent au jalon 5.
    </x-alert>
@endsection
