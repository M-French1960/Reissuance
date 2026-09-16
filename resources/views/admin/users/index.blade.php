@extends('layouts.app')
@section('title', __('admin.users.title'))

@section('content')
    <h1>{{ __('admin.users.title') }}</h1>

    <x-flash />
    @if ($errors->any())
        <x-alert variant="danger" :title="__('officer.action_impossible')">
            <ul class="alert__list">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <form method="GET" action="{{ route('admin.users.index') }}" class="toolbar">
            <x-field name="recherche" :label="__('admin.users.search')" :value="request('recherche')"
                     :hint="__('admin.users.search_hint')" />
            <div class="field">
                <label class="field__label" for="role">{{ __('admin.users.role') }}</label>
                <select class="field__control" id="role" name="role">
                    <option value="">{{ __('admin.users.all') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="statut">{{ __('admin.users.status') }}</label>
                <select class="field__control" id="statut" name="statut">
                    <option value="">{{ __('admin.users.all') }}</option>
                    @foreach (['pending', 'active', 'suspended', 'disabled'] as $value)
                        <option value="{{ $value }}" @selected(request('statut') === $value)>{{ __('enums.account_status.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('common.filter') }}</x-button>
            <x-button href="{{ route('admin.users.create') }}" variant="primary">{{ __('admin.users.create') }}</x-button>
        </form>
    </x-card>

    <x-card>
        @if ($users->isEmpty())
            <x-empty-state :title="__('admin.users.no_match_title')">{{ __('admin.users.no_match_body') }}</x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.users.list_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.users.list_aria') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.name') }}</th>
                            <th scope="col">{{ __('admin.users.role') }}</th>
                            <th scope="col">{{ __('admin.users.attachment') }}</th>
                            <th scope="col">{{ __('admin.users.status') }}</th>
                            <th scope="col">{{ __('admin.users.two_factor') }}</th>
                            <th scope="col">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>{{ $user->name }}<br><span class="field__hint">{{ $user->email }}</span></td>
                                <td>{{ $user->role->label() }}</td>
                                <td>{{ $user->center?->name ?? $user->commune?->name }}</td>
                                <td>
                                    <span class="badge badge--{{ $user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'attention' : ($user->status === 'pending' ? 'waiting' : 'neutral')) }}">
                                        {{ __('enums.account_status.'.$user->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($user->two_factor_confirmed_at)
                                        <span class="badge badge--success">{{ __('admin.users.two_factor_configured') }}</span>
                                    @elseif ($user->role->requiresTwoFactor())
                                        <span class="badge badge--danger">{{ __('admin.users.two_factor_required') }}</span>
                                    @else
                                        <span class="badge badge--neutral">{{ __('admin.users.two_factor_optional') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        @can('changeStatus', $user)
                                            <details>
                                                <summary>{{ __('admin.users.change_status') }}</summary>
                                                <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                                    @csrf @method('PATCH')
                                                    <div class="field">
                                                        <label class="field__label" for="status-{{ $user->id }}">{{ __('admin.users.new_status') }}</label>
                                                        <select class="field__control" id="status-{{ $user->id }}" name="status" required>
                                                            @foreach (['active', 'pending', 'suspended', 'disabled'] as $value)
                                                                <option value="{{ $value }}">{{ __('enums.account_status.'.$value) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <x-field name="reason" :label="__('admin.users.status_reason')"
                                                             :hint="__('admin.users.status_reason_hint')" required
                                                             :id="'reason-'.$user->id" />
                                                    <x-button type="submit" variant="secondary">{{ __('common.save') }}</x-button>
                                                </form>
                                            </details>
                                        @endcan
                                        @can('triggerPasswordReset', $user)
                                            <form method="POST" action="{{ route('admin.users.reset', $user) }}">
                                                @csrf
                                                <x-button type="submit" variant="secondary">{{ __('admin.users.send_link') }}</x-button>
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
