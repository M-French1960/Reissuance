@extends('layouts.app')
@section('title', __('dashboard.admin.title'))

@section('content')
    <h1>{{ __('dashboard.admin.heading') }}</h1>

    <x-alert variant="attention" title="{{ __('dashboard.admin.scope_title') }}">
        {!! __('dashboard.admin.scope_body', ['strong' => '<strong>'.e(__('dashboard.admin.scope_strong')).'</strong>']) !!}
    </x-alert>

    <x-card :title="__('dashboard.admin.accounts_by_role')">
        <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('dashboard.admin.accounts_distribution') }}">
            <table>
                <caption class="visually-hidden">{{ __('dashboard.admin.accounts_distribution') }}</caption>
                <thead><tr>
                    <th scope="col">{{ __('common.role') }}</th>
                    <th scope="col">{{ __('common.status') }}</th>
                    <th scope="col">{{ __('dashboard.admin.count') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($accounts as $row)
                        <tr>
                            {{-- $row is a User model: `role` and `status` already come
                                 back converted by the model casts, even behind a
                                 selectRaw. Converting them again raised a TypeError,
                                 and this screen returned 500 from milestone 2 on. --}}
                            <td data-label="{{ __('common.role') }}">{{ $row->role->label() }}</td>
                            <td data-label="{{ __('common.status') }}">{{ __('enums.account_status.'.$row->status) }}</td>
                            <td data-label="{{ __('dashboard.admin.count') }}">{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="grid grid--2">
        <x-card :title="__('dashboard.admin.manage_accounts')">
            <p>{{ __('dashboard.admin.manage_accounts_body') }}</p>
            <x-button href="{{ route('admin.users.index') }}" variant="secondary">{{ __('common.open') }}</x-button>
        </x-card>
        <x-card :title="__('common.audit_log')">
            <p>{{ __('dashboard.admin.audit_body') }}</p>
            <x-button href="{{ route('admin.audit.index') }}" variant="secondary">{{ __('dashboard.admin.view') }}</x-button>
        </x-card>
    </div>
@endsection
