@extends('layouts.app')
@section('title', 'Mes demandes')

@section('content')
    <h1>Mes demandes</h1>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        @if ($requests->isEmpty())
            <x-empty-state title="Aucune demande pour l'instant"
                           :action="route('citizen.requests.start')"
                           action-label="Faire une demande">
                Vous n'avez pas encore déposé de demande de réédition d'acte de naissance.
            </x-empty-state>
        @else
            <p><x-button href="{{ route('citizen.requests.start') }}" variant="primary">Faire une nouvelle demande</x-button></p>

            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Liste de mes demandes</caption>
                    <thead>
                        <tr>
                            <th scope="col">Référence</th><th scope="col">Statut</th>
                            <th scope="col">Centre</th><th scope="col">Déposée le</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td><x-status-badge :status="$demande->status" /></td>
                                <td>{{ $demande->center?->name ?? '—' }}</td>
                                <td>{{ $demande->submitted_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if ($demande->isDraft())
                                        <a href="{{ route('citizen.requests.step', ['reissuanceRequest' => $demande, 'step' => min($demande->last_completed_step + 1, 4)]) }}">Reprendre</a>
                                    @else
                                        <a href="{{ route('citizen.requests.show', $demande) }}">Suivre</a>
                                    @endif
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
