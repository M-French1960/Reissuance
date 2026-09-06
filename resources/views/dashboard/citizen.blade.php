@extends('layouts.app')
@section('title', 'Mon espace')

@section('content')
    <h1>Mon espace</h1>
    <p>Suivez vos demandes de réédition d'acte de naissance.</p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-card title="Mes demandes">
        @forelse ($requests as $request)
            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Liste de mes demandes</caption>
                    <thead>
                        <tr><th scope="col">Référence</th><th scope="col">Statut</th><th scope="col">Déposée le</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr>
                                <td>{{ $r->reference }}</td>
                                <td><x-status-badge :status="$r->status" /></td>
                                <td>{{ $r->submitted_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @break
        @empty
            <x-empty-state title="Aucune demande pour l'instant">
                Le dépôt d'une demande sera disponible au prochain jalon.
            </x-empty-state>
        @endforelse
    </x-card>
@endsection
