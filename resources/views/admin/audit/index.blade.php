@extends('layouts.app')
@section('title', "Journal d'audit")

@section('content')
    <h1>Journal d'audit</h1>

    <x-alert variant="attention" title="Métadonnées seulement">
        Ce journal indique <strong>qui</strong> a fait <strong>quoi</strong> et
        <strong>quand</strong>. Il ne contient aucun contenu de dossier : ni
        photographie, ni numéro de pièce. Le journal est en ajout seul et ne peut
        être ni modifié ni supprimé, y compris par vous.
    </x-alert>

    <x-card>
        <form method="GET" action="{{ route('admin.audit.index') }}" class="toolbar">
            <x-field name="action" label="Action" :value="request('action')" hint="Par exemple : account" />
            <x-field name="depuis" label="Depuis le" type="date" :value="request('depuis')" />
            <x-button type="submit" variant="secondary">Filtrer</x-button>
        </form>
    </x-card>

    <x-card>
        @if ($logs->isEmpty())
            <x-empty-state title="Aucune entrée">Aucune action ne correspond à ces filtres.</x-empty-state>
        @else
            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Entrées du journal d'audit</caption>
                    <thead>
                        <tr>
                            <th scope="col">Horodatage</th><th scope="col">Acteur</th>
                            <th scope="col">Action</th><th scope="col">Entité</th>
                            <th scope="col">Transition</th><th scope="col">Adresse IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->actor?->name ?? 'Inconnu' }}<br>
                                    <span class="field__hint">{{ $log->actor_role }}</span></td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->auditable_type }} @if ($log->auditable_id) #{{ $log->auditable_id }} @endif</td>
                                <td>@if ($log->from_status) {{ $log->from_status }} &rarr; {{ $log->to_status }} @else — @endif</td>
                                <td>{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links('pagination') }}
        @endif
    </x-card>
@endsection
