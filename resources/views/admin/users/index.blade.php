@extends('layouts.app')
@section('title', 'Comptes')

@section('content')
    <h1>Comptes</h1>

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
        <form method="GET" action="{{ route('admin.users.index') }}" class="toolbar">
            <x-field name="recherche" label="Rechercher" :value="request('recherche')"
                     hint="Nom ou adresse électronique" />
            <div class="field">
                <label class="field__label" for="role">Rôle</label>
                <select class="field__control" id="role" name="role">
                    <option value="">Tous</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="statut">Statut</label>
                <select class="field__control" id="statut" name="statut">
                    <option value="">Tous</option>
                    @foreach (['pending' => 'En attente de configuration', 'active' => 'Actif', 'suspended' => 'Suspendu', 'disabled' => 'Désactivé'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('statut') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">Filtrer</x-button>
            <x-button href="{{ route('admin.users.create') }}" variant="primary">Créer un compte</x-button>
        </form>
    </x-card>

    <x-card>
        @if ($users->isEmpty())
            <x-empty-state title="Aucun compte ne correspond">Modifiez les filtres pour élargir la recherche.</x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="Liste des comptes">
                <table>
                    <caption class="visually-hidden">Liste des comptes</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nom</th><th scope="col">Rôle</th>
                            <th scope="col">Rattachement</th><th scope="col">Statut</th>
                            <th scope="col">2FA</th><th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>{{ $user->name }}<br><span class="field__hint">{{ $user->email }}</span></td>
                                <td>{{ $user->role->label() }}</td>
                                <td>{{ $user->center?->name ?? $user->commune?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge badge--{{ $user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'attention' : ($user->status === 'pending' ? 'waiting' : 'neutral')) }}">
                                        {{ ['pending' => 'En attente de configuration', 'active' => 'Actif', 'suspended' => 'Suspendu', 'disabled' => 'Désactivé'][$user->status] }}
                                    </span>
                                </td>
                                <td>
                                    @if ($user->two_factor_confirmed_at)
                                        <span class="badge badge--success">Configurée</span>
                                    @elseif ($user->role->requiresTwoFactor())
                                        <span class="badge badge--danger">Requise</span>
                                    @else
                                        <span class="badge badge--neutral">Facultative</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        @can('changeStatus', $user)
                                            <details>
                                                <summary>Changer le statut</summary>
                                                <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                                    @csrf @method('PATCH')
                                                    <div class="field">
                                                        <label class="field__label" for="status-{{ $user->id }}">Nouveau statut</label>
                                                        <select class="field__control" id="status-{{ $user->id }}" name="status" required>
                                                            <option value="active">Actif</option>
                                                            <option value="pending">En attente de configuration</option>
                                                            <option value="suspended">Suspendu</option>
                                                            <option value="disabled">Désactivé</option>
                                                        </select>
                                                    </div>
                                                    <x-field name="reason" label="Motif"
                                                             hint="Figurera au journal d'audit. 10 caractères minimum." required
                                                             :id="'reason-'.$user->id" />
                                                    <x-button type="submit" variant="secondary">Enregistrer</x-button>
                                                </form>
                                            </details>
                                        @endcan
                                        @can('triggerPasswordReset', $user)
                                            <form method="POST" action="{{ route('admin.users.reset', $user) }}">
                                                @csrf
                                                <x-button type="submit" variant="secondary">Envoyer un lien</x-button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links('pagination') }}
        @endif
    </x-card>
@endsection
