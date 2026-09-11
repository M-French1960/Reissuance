@extends('layouts.app')
@section('title', $steps[$step])

@section('content')
    <h1>Vérification — {{ $demande->reference }}</h1>
    <p class="u-note">
        Demandeur : {{ $demande->full_name_at_birth ?? '—' }} ·
        Statut : {{ $demande->status->label() }}
        @if ($demande->verification_cycle > 1)
            · <strong>Passe n°{{ $demande->verification_cycle }}</strong> (dossier retourné par le maire)
        @endif
    </p>

    <x-step-indicator :steps="$steps" :current="$step" />

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">Action impossible</p>
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    {{--
        Trois raisons distinctes de ne pas pouvoir décider, et trois messages
        distincts. La version précédente repliait tout sur « pris en charge par
        un autre agent », y compris quand PERSONNE ne l'avait pris : elle
        affirmait une chose fausse et masquait la seule action possible
        (D-055).
    --}}
    @unless ($peutDecider)
        @if ($peutPrendreEnCharge)
            <x-alert variant="attention" title="Dossier à prendre en charge">
                Personne ne traite ce dossier. Prenez-le en charge pour pouvoir
                le vérifier et décider.
                <form method="POST" action="{{ route('officer.verification.claim', $demande) }}" class="alert__action">
                    @csrf
                    <x-button type="submit">Prendre en charge</x-button>
                </form>
            </x-alert>
        @elseif ($demande->assignedOfficer !== null)
            <x-alert variant="attention" title="Lecture seule">
                Ce dossier est pris en charge par
                {{ $demande->assignedOfficer->name }}.
                Vous pouvez le consulter — la consultation est journalisée — mais
                la décision lui revient.
            </x-alert>
        @else
            <x-alert variant="attention" title="Dossier sans agent affecté">
                Ce dossier est à l'état « {{ $demande->status->label() }} » et
                n'est affecté à personne : il ne peut être ni pris en charge ni
                décidé en l'état. Signalez-le à l'administrateur.
            </x-alert>
        @endif
    @endunless

    @yield('etape')

    <p class="u-note">Raccourcis : <kbd>→</kbd> ou <kbd>n</kbd> pour l'étape suivante, <kbd>←</kbd> ou <kbd>p</kbd> pour la précédente.</p>

    <nav class="actions" aria-label="Navigation entre les étapes" data-step-nav>
        @if ($step > 1)
            <x-button href="{{ route('officer.verification.step', ['reissuanceRequest' => $demande, 'step' => $step - 1]) }}" variant="secondary" data-step-prev>Étape précédente</x-button>
        @else
            <x-button href="{{ route('officer.queue') }}" variant="secondary">Retour à la file</x-button>
        @endif
        @if ($step < 5)
            <x-button href="{{ route('officer.verification.step', ['reissuanceRequest' => $demande, 'step' => $step + 1]) }}" variant="secondary" data-step-next>Étape suivante</x-button>
        @endif
    </nav>
@endsection
