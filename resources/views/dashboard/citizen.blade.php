@extends('layouts.app')
@section('title', 'Mon espace')

@section('content')
    {{--
        L'ECRAN QUI SUIT LA CONNEXION D'UN CITOYEN (D-073).

        Il est reste une ebauche du jalon 2 : son etat vide annoncait « Le dépôt
        d'une demande sera disponible au prochain jalon » — il disait donc au
        citoyen qu'il ne pouvait pas faire la seule chose pour laquelle il
        venait. Et il n'offrait aucune action, pas meme un lien.
    --}}
    <h1>Mon espace</h1>
    <p>Suivez vos demandes de réédition d'acte de naissance.</p>

    <x-flash />

    @unless ($profilComplet)
        {{-- Dit avant qu'il ne s'y heurte : le depot exige un profil complet. --}}
        <x-alert variant="attention" title="Complétez votre profil">
            <p>Votre profil doit être complet avant de déposer une demande.</p>
            <div class="row-actions">
                <x-button href="{{ route('citizen.profile.edit') }}" variant="primary">Compléter mon profil</x-button>
            </div>
        </x-alert>
    @endunless

    <x-card title="Mes demandes">
        @if ($requests->isEmpty())
            <x-empty-state title="Aucune demande pour l'instant">
                <p>Déposez votre première demande de réédition d'acte de naissance.</p>
                <form method="POST" action="{{ route('citizen.requests.start') }}">
                    @csrf
                    <x-button type="submit" variant="primary">Faire une demande</x-button>
                </form>
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="Liste de mes demandes">
                <table>
                    <caption class="visually-hidden">Liste de mes demandes</caption>
                    <thead>
                        <tr>
                            <th scope="col">Référence</th>
                            <th scope="col">Statut</th>
                            <th scope="col">Déposée le</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr>
                                <td>{{ $r->reference }}</td>
                                <td><x-status-badge :status="$r->status" /></td>
                                <td>{{ $r->submitted_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                <td>
                                    {{-- Un tableau sans action est une impasse : on y menait
                                         sans jamais pouvoir ouvrir une demande. --}}
                                    @if ($r->status === \App\Enums\RequestStatus::Draft)
                                        <a href="{{ route('citizen.requests.step', [$r, $r->last_completed_step ? min($r->last_completed_step + 1, 4) : 1]) }}">Reprendre</a>
                                    @else
                                        <a href="{{ route('citizen.requests.show', $r) }}">Suivre</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row-actions u-stack-top">
                <form method="POST" action="{{ route('citizen.requests.start') }}">
                    @csrf
                    <x-button type="submit" variant="primary">Faire une nouvelle demande</x-button>
                </form>
                <x-button href="{{ route('citizen.requests.index') }}" variant="secondary">Toutes mes demandes</x-button>
            </div>
        @endif
    </x-card>
@endsection
