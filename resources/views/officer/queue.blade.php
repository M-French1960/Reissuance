@extends('layouts.app')
@section('title', 'File de traitement')

@section('content')
    <h1>File de traitement</h1>
    <p>Centre : <strong>{{ auth()->user()->center?->name ?? '—' }}</strong>.
    Vous ne voyez que les demandes de ce centre.</p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert variant="danger" title="Action impossible">
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </x-alert>
    @endif

    <div class="grid grid--4">
        @foreach ([\App\Enums\RequestStatus::Pending, \App\Enums\RequestStatus::UnderReview, \App\Enums\RequestStatus::AwaitingSignature, \App\Enums\RequestStatus::Escalated] as $s)
            <x-card>
                <p class="stat__label">{{ $s->label() }}</p>
                <p class="stat__value">{{ $counts[$s->value] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card>
        <form method="GET" action="{{ route('officer.queue') }}" class="toolbar">
            <x-field name="recherche" label="Rechercher" :value="request('recherche')"
                     hint="Référence ou nom à la naissance" />
            <div class="field">
                <label class="field__label" for="statut">Statut</label>
                <select class="field__control" id="statut" name="statut">
                    <option value="">Tous</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('statut') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="assignation">Assignation</label>
                <select class="field__control" id="assignation" name="assignation">
                    <option value="">Toutes</option>
                    <option value="moi" @selected(request('assignation') === 'moi')>Les miennes</option>
                    <option value="libre" @selected(request('assignation') === 'libre')>Non prises en charge</option>
                </select>
            </div>
            <x-field name="depuis" label="Déposées depuis le" type="date" :value="request('depuis')" />
            <x-button type="submit" variant="secondary">Filtrer</x-button>
        </form>
    </x-card>

    <x-card>
        @if ($requests->isEmpty())
            <x-empty-state title="Aucune demande ne correspond">
                Élargissez les filtres, ou revenez plus tard : les nouvelles demandes
                apparaissent ici dès leur dépôt.
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="Demandes du centre">
                <table>
                    <caption class="visually-hidden">Demandes du centre</caption>
                    <thead>
                        <tr>
                            @foreach (['reference' => 'Référence', 'status' => 'Statut', 'submitted_at' => 'Déposée le'] as $col => $libelle)
                                <th scope="col" @if ($sort === $col) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    <a href="{{ route('officer.queue', array_merge(request()->query(), ['tri' => $col, 'sens' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc'])) }}">
                                        {{ $libelle }}
                                        @if ($sort === $col)<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif
                                    </a>
                                </th>
                            @endforeach
                            <th scope="col">Demandeur</th>
                            <th scope="col">Prise en charge</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td><x-status-badge :status="$demande->status" /></td>
                                <td>{{ $demande->submitted_at?->translatedFormat('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $demande->full_name_at_birth ?? '—' }}</td>
                                <td>
                                    @if ($demande->assignedOfficer)
                                        {{ $demande->assigned_officer_id === auth()->id() ? 'Vous' : $demande->assignedOfficer->name }}
                                    @else
                                        {{--
                                            « Non assignée » et non « Personne » : la colonne
                                            voisine porte le NOM du demandeur, et « Personne »
                                            s'y lit comme un nom propre plutot que comme
                                            « aucun agent ». Releve en regardant la file.
                                        --}}
                                        <span class="u-note">Non assignée</span>
                                    @endif
                                </td>
                                <td>
                                    @can('claim', $demande)
                                        <form method="POST" action="{{ route('officer.verification.claim', $demande) }}">
                                            @csrf
                                            <x-button type="submit" variant="primary">Prendre en charge</x-button>
                                        </form>
                                    @elsecan('view', $demande)
                                        <a href="{{ route('officer.verification.step', ['reissuanceRequest' => $demande, 'step' => 1]) }}">Ouvrir</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links('pagination') }}
        @endif
    </x-card>
@endsection
