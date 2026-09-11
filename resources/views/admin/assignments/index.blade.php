@extends('layouts.app')
@section('title', 'Affectations')

@section('content')
    <h1>Affectations</h1>

    <p class="u-note">
        Cet écran ne donne pas accès aux dossiers : il montre <strong>qui tient
        quel dossier</strong>, et rien de ce que le dossier contient.
    </p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert variant="danger" title="Action impossible">
            <ul class="alert__list">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <h2 class="card__title">Affectations bloquées</h2>

        @if ($bloquees->isEmpty())
            <x-empty-state title="Aucune affectation bloquée">
                Tous les dossiers en cours sont tenus par un agent en mesure de les traiter.
            </x-empty-state>
        @else
            <x-alert variant="attention" title="Ces dossiers n'avancent plus">
                L'agent affecté ne peut plus les traiter — compte inactif, ou
                rattaché à un autre centre. Tant que l'affectation n'est pas
                libérée, <strong>aucun autre agent du centre ne peut les
                reprendre</strong>, et le demandeur attend sans le savoir.
            </x-alert>

            <div class="table-wrap" tabindex="0" role="group" aria-label="Affectations bloquées">
                <table>
                    <caption class="visually-hidden">Affectations bloquées</caption>
                    <thead>
                        <tr>
                            <th scope="col">Référence</th>
                            <th scope="col">Centre</th>
                            <th scope="col">Agent affecté</th>
                            <th scope="col">Pourquoi</th>
                            <th scope="col">Déposée le</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bloquees as $affectation)
                            <tr>
                                <td>{{ $affectation->reference }}</td>
                                <td>{{ $affectation->center?->name ?? '—' }}</td>
                                <td>
                                    {{ $affectation->assignedOfficer?->name ?? '—' }}<br>
                                    <span class="field__hint">{{ $affectation->assignedOfficer?->email }}</span>
                                </td>
                                <td>
                                    @if (! $affectation->assignedOfficer?->isActive())
                                        <span class="badge badge--attention">Compte inactif</span>
                                    @else
                                        <span class="badge badge--attention">Rattaché ailleurs</span>
                                    @endif
                                </td>
                                <td>{{ $affectation->submitted_at?->translatedFormat('j F Y') ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.assignments.release', $affectation) }}">
                                        @csrf
                                        <x-button type="submit" variant="secondary">Libérer</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card>
        <h2 class="card__title">Affectations en cours</h2>

        @if ($actives->isEmpty())
            <x-empty-state title="Aucun dossier pris en charge">
                Aucun agent ne tient de dossier actuellement.
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="Affectations en cours">
                <table>
                    <caption class="visually-hidden">Affectations en cours</caption>
                    <thead>
                        <tr>
                            <th scope="col">Référence</th>
                            <th scope="col">Centre</th>
                            <th scope="col">Agent affecté</th>
                            <th scope="col">Déposée le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($actives as $affectation)
                            <tr>
                                <td>{{ $affectation->reference }}</td>
                                <td>{{ $affectation->center?->name ?? '—' }}</td>
                                <td>{{ $affectation->assignedOfficer?->name ?? '—' }}</td>
                                <td>{{ $affectation->submitted_at?->translatedFormat('j F Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
