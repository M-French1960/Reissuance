@extends('layouts.app')
@section('title', 'Espace du maire')

@section('content')
    {{--
        L'ECRAN QUI SUIT LA CONNEXION D'UN MAIRE (D-073).

        Il est reste une ebauche du jalon 2 : deux compteurs, puis « Jalon 2 —
        Les deux files et la signature électronique arrivent au jalon 5 ». Les
        deux existent. Et aucun compteur ne menait a la file de signature.
    --}}
    <h1>Signature des actes</h1>
    <p>Commune : <strong>{{ auth()->user()->commune?->name ?? '—' }}</strong>.
    Vous ne voyez que les demandes prêtes à signer ou escaladées.</p>

    <x-flash />

    <div class="grid grid--2">
        @foreach ([\App\Enums\RequestStatus::AwaitingSignature, \App\Enums\RequestStatus::Escalated] as $status)
            <x-card>
                <p class="stat__label">{{ $status->label() }}</p>
                <p class="stat__value">{{ $counts[$status->value] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card title="Signer un acte">
        <p>
            Vous signez le <strong>projet d'acte rédigé par l'officier</strong>&nbsp;:
            ouvrez-le avant de décider. Chaque signature demande votre code
            d'authentification, ou votre appareil si vous en avez enrôlé un.
        </p>
        <div class="row-actions">
            <x-button href="{{ route('mayor.dashboard') }}" variant="primary">Ouvrir la file de signature</x-button>
            <x-button href="{{ route('two-factor.setup') }}" variant="secondary">Enrôler un appareil pour signer</x-button>
        </div>
    </x-card>
@endsection
