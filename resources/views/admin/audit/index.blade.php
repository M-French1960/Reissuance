@extends('layouts.app')
@section('title', __('admin.audit.title'))

@section('content')
    <h1>{{ __('admin.audit.title') }}</h1>

    <x-alert variant="attention" :title="__('admin.audit.metadata_title')">
        {!! __('admin.audit.metadata_body', [
            'who' => '<strong>'.e(__('admin.audit.metadata_who')).'</strong>',
            'what' => '<strong>'.e(__('admin.audit.metadata_what')).'</strong>',
            'when' => '<strong>'.e(__('admin.audit.metadata_when')).'</strong>',
        ]) !!}
    </x-alert>

    <x-card>
        <form method="GET" action="{{ route('admin.audit.index') }}" class="toolbar">
            <x-field name="action" :label="__('admin.audit.action')" :value="request('action')" :hint="__('admin.audit.action_hint')" />
            <x-field name="depuis" :label="__('admin.audit.since')" type="date" :value="request('depuis')" />
            <x-button type="submit" variant="secondary">{{ __('common.filter') }}</x-button>
        </form>
    </x-card>

    <x-card>
        @if ($logs->isEmpty())
            <x-empty-state :title="__('admin.audit.empty_title')">{{ __('admin.audit.empty_body') }}</x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.audit.entries_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.audit.entries_aria') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.audit.timestamp') }}</th>
                            <th scope="col">{{ __('admin.audit.actor') }}</th>
                            <th scope="col">{{ __('admin.audit.action') }}</th>
                            <th scope="col">{{ __('admin.audit.entity') }}</th>
                            <th scope="col">{{ __('admin.audit.transition') }}</th>
                            <th scope="col">{{ __('admin.audit.ip_address') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->actor?->name ?? __('admin.audit.unknown_actor') }}<br>
                                    <span class="field__hint">{{ $log->actor_role }}</span></td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->auditable_type }} @if ($log->auditable_id) #{{ $log->auditable_id }} @endif</td>
                                <td>@if ($log->from_status) {{ $log->from_status }} &rarr; {{ $log->to_status }} @endif</td>
                                <td>{{ $log->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links('pagination') }}
        @endif
    </x-card>
@endsection
