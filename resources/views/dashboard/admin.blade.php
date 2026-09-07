@extends('layouts.app')
@section('title', 'Administration')

@section('content')
    <h1>Administration</h1>

    <x-alert variant="attention" title="Périmètre de ce compte">
        Votre rôle donne accès à la gestion des comptes et aux métadonnées du
        journal d'audit. Il ne donne accès à <strong>aucun</strong> dossier de
        citoyen : ni pièce d'identité, ni photographie, ni numéro de pièce.
    </x-alert>

    <x-card title="Comptes par rôle et statut">
        <div class="table-wrap" tabindex="0" role="group" aria-label="Répartition des comptes">
            <table>
                <caption class="visually-hidden">Répartition des comptes</caption>
                <thead><tr><th scope="col">Rôle</th><th scope="col">Statut</th><th scope="col">Nombre</th></tr></thead>
                <tbody>
                    @foreach ($accounts as $row)
                        <tr>
                            {{-- $row est un modele User : `role` et `status`
                                 sortent deja convertis par les casts du
                                 modele, meme derriere un selectRaw. Les
                                 reconvertir levait une TypeError, et cet
                                 ecran renvoyait 500 depuis le jalon 2. --}}
                            <td>{{ $row->role->label() }}</td>
                            <td>{{ $row->status }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="grid grid--2">
        <x-card title="Gérer les comptes">
            <p>Créer, activer, suspendre ou réaffecter un officier ou un maire.</p>
            <x-button href="{{ route('admin.users.index') }}" variant="secondary">Ouvrir</x-button>
        </x-card>
        <x-card title="Journal d'audit">
            <p>Qui a consulté quel dossier et quand. Le contenu des dossiers reste inaccessible.</p>
            <x-button href="{{ route('admin.audit.index') }}" variant="secondary">Consulter</x-button>
        </x-card>
    </div>
@endsection
