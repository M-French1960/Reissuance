@extends('layouts.app')
@section('title', 'Mes demandes')

@section('content')
    <h1>Mes demandes</h1>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        {{--
            UN FORMULAIRE, PAS UN LIEN (D-071).

            `citizen.requests.start` CREE un brouillon : la route est en POST,
            et elle doit le rester — une route qui cree une ressource sur un
            GET serait declenchee par un simple prechargement de navigateur.
            Ces deux boutons etaient des <a href>, donc des GET : ils rendaient
            404, et le citoyen ne pouvait pas commencer sa demande du tout.
        --}}
        @if ($requests->isEmpty())
            <x-empty-state title="Aucune demande pour l'instant">
                <p>Vous n'avez pas encore déposé de demande de réédition d'acte de naissance.</p>
                <form method="POST" action="{{ route('citizen.requests.start') }}">
                    @csrf
                    <x-button type="submit" variant="primary">Faire une demande</x-button>
                </form>
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('citizen.requests.start') }}">
                @csrf
                <x-button type="submit" variant="primary">Faire une nouvelle demande</x-button>
            </form>

            <div class="table-wrap" tabindex="0" role="group" aria-label="Liste de mes demandes">
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
