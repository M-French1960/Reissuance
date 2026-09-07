@extends('layouts.app')
@section('title', 'Signature des actes')

@section('content')
    <h1>Signature des actes</h1>
    <p>Commune : <strong>{{ auth()->user()->commune?->name ?? '—' }}</strong>.
    Vous ne voyez que les dossiers prêts à signer ou escaladés.</p>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-alert variant="attention" title="Signature de démonstration">
        Le prestataire de signature configuré est un adaptateur factice. Tout
        acte délivré porte en clair la mention <strong>« sans valeur
        juridique »</strong>. La valeur légale d'un acte d'état civil signé
        électroniquement au Cameroun reste à confirmer.
    </x-alert>

    <x-card title="Prêtes à signer ({{ $aSigner->total() }})">
        @if ($aSigner->isEmpty())
            <x-empty-state title="Aucun dossier en attente de signature">
                Les dossiers validés par un officier de votre commune apparaîtront ici.
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Dossiers prêts à signer</caption>
                    <thead><tr>
                        <th scope="col">Référence</th><th scope="col">Demandeur</th>
                        <th scope="col">Centre</th><th scope="col">Déposée le</th><th scope="col">Action</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($aSigner as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td>{{ $demande->full_name_at_birth ?? '—' }}</td>
                                <td>{{ $demande->center?->name ?? '—' }}</td>
                                <td>{{ $demande->submitted_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                <td><a href="{{ route('mayor.review', $demande) }}">Examiner</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $aSigner->links('pagination') }}
        @endif
    </x-card>

    <x-card title="Escaladées ({{ $escaladees->total() }})">
        <p class="u-note">Dossiers que l'officier n'a pas tranchés et qui appellent votre arbitrage.</p>

        @if ($escaladees->isEmpty())
            <x-empty-state title="Aucun dossier escaladé">
                Rien n'appelle votre arbitrage pour l'instant.
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Dossiers escaladés</caption>
                    <thead><tr>
                        <th scope="col">Référence</th><th scope="col">Demandeur</th>
                        <th scope="col">Motif de l'escalade</th><th scope="col">Action</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($escaladees as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td>{{ $demande->full_name_at_birth ?? '—' }}</td>
                                <td>{{ $demande->decisions->first()?->reason ?? '—' }}</td>
                                <td><a href="{{ route('mayor.review', $demande) }}">Arbitrer</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $escaladees->links('pagination') }}
        @endif
    </x-card>
@endsection
