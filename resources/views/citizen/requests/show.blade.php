@extends('layouts.app')
@section('title', 'Suivi de ma demande')

@section('content')
    <h1>Demande {{ $demande->reference }}</h1>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <p>
            <x-status-badge :status="$demande->status" />
            @if ($demande->submitted_at)
                <span class="field__hint">Déposée le {{ $demande->submitted_at->translatedFormat('d F Y') }}</span>
            @endif
        </p>
        <p class="field__hint">
            Centre d'état civil : {{ $demande->center?->name ?? '—' }}
            @if ($demande->center?->commune) — commune de {{ $demande->center->commune->name }} @endif
        </p>
    </x-card>

    <x-card title="Où en est ma demande">
        <ol class="timeline">
            @foreach ($etapes as $etape)
                <li class="timeline__item timeline__item--{{ $etape['etat'] }}">
                    <span class="timeline__marker" aria-hidden="true">
                        @if ($etape['etat'] === 'fait') ✓
                        @elseif ($etape['etat'] === 'arrete') ✕
                        @else {{ $loop->iteration }}
                        @endif
                    </span>
                    <div>
                        <strong>{{ $etape['titre'] }}</strong>
                        {{-- L'état n'est jamais porté par la seule couleur (WCAG 1.4.1). --}}
                        <span class="timeline__state">
                            @switch ($etape['etat'])
                                @case('fait') — terminé @break
                                @case('en_cours') — en cours @break
                                @case('arrete') — non atteint @break
                                @default — à venir
                            @endswitch
                        </span>
                        <br>
                        <span class="field__hint">{{ $etape['detail'] }}</span>
                        @if ($etape['date'])
                            <br><span class="field__hint">{{ $etape['date'] }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- Aucun délai chiffré : la question D8 de COMPLIANCE_OPEN_QUESTIONS.md
             est ouverte, et inventer un délai serait pire que ne rien annoncer. --}}
        <p class="field__hint">Vous serez averti à chaque changement d'étape.</p>
    </x-card>

    @if ($demande->signature)
        <x-card title="Mon acte">
            <p>Votre acte a été signé le
            {{ $demande->signature->signed_at?->translatedFormat('d F Y') }}
            par {{ $demande->signature->mayor?->name }}.</p>

            @unless ($demande->signature->legally_binding)
                <x-alert variant="attention" title="Document de démonstration">
                    Ce document porte la mention « sans valeur juridique » et ne
                    peut être présenté à aucune administration. La plateforme
                    fonctionne avec un prestataire de signature factice.
                </x-alert>
            @endunless

            <div class="row-actions">
                <x-button href="{{ route('acts.document', $demande->signature) }}" variant="primary">Télécharger mon acte</x-button>
                <x-button href="{{ route('acts.proof', $demande->signature) }}" variant="secondary">Preuve de signature</x-button>
            </div>
        </x-card>
    @endif

    <x-card title="Mes pièces">
        <div class="grid grid--2">
            @forelse ($demande->attachments as $piece)
                <div>
                    <p><strong>{{ $piece->kind === 'selfie' ? 'Votre photo' : "Votre pièce d'identité" }}</strong></p>
                    <img class="capture__preview" src="{{ route('citizen.attachments.show', $piece) }}"
                         alt="{{ $piece->kind === 'selfie' ? 'Votre photo' : 'Votre pièce d\'identité' }}">
                    <p class="field__hint">{{ round($piece->size_bytes / 1024) }} Ko</p>
                </div>
            @empty
                <p class="field__hint">Aucune pièce enregistrée.</p>
            @endforelse
        </div>
    </x-card>

    <p class="u-return"><a href="{{ route('citizen.requests.index') }}">Retour à mes demandes</a></p>
@endsection
