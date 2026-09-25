@extends('layouts.app')
@section('title', __('admin.assignments.title'))

@section('content')
    <h1>{{ __('admin.assignments.title') }}</h1>

    <p class="u-note">
        {!! __('admin.assignments.intro', ['strong' => '<strong>'.e(__('admin.assignments.intro_strong')).'</strong>']) !!}
    </p>

    <x-flash />
    @if ($errors->any())
        <x-alert variant="danger" :title="__('officer.action_impossible')">
            <ul class="alert__list">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <h2 class="card__title">{{ __('admin.assignments.stuck_title') }}</h2>

        @if ($bloquees->isEmpty())
            <x-empty-state :level="3" :title="__('admin.assignments.stuck_none_title')">
                {{ __('admin.assignments.stuck_none_body') }}
            </x-empty-state>
        @else
            <x-alert variant="attention" :title="__('admin.assignments.stuck_alert_title')">
                {!! __('admin.assignments.stuck_alert_body', ['strong' => '<strong>'.e(__('admin.assignments.stuck_alert_strong')).'</strong>']) !!}
            </x-alert>

            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.assignments.stuck_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.assignments.stuck_aria') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">{{ __('common.centre') }}</th>
                            <th scope="col">{{ __('admin.assignments.assigned_agent') }}</th>
                            <th scope="col">{{ __('admin.assignments.why') }}</th>
                            <th scope="col">{{ __('common.submitted_on') }}</th>
                            <th scope="col">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bloquees as $affectation)
                            <tr>
                                <td data-label="{{ __('common.reference') }}">{{ $affectation->reference }}</td>
                                <td data-label="{{ __('common.centre') }}">{{ $affectation->center?->name }}</td>
                                <td data-label="{{ __('admin.assignments.assigned_agent') }}">
                                    {{ $affectation->assignedOfficer?->name }}<br>
                                    <span class="field__hint">{{ $affectation->assignedOfficer?->email }}</span>
                                </td>
                                <td data-label="{{ __('admin.assignments.why') }}">
                                    <span class="badge badge--attention">
                                        {{ $affectation->assignedOfficer?->isActive()
                                            ? __('admin.assignments.attached_elsewhere')
                                            : __('admin.assignments.account_inactive') }}
                                    </span>
                                </td>
                                <td data-label="{{ __('common.submitted_on') }}">{{ $affectation->submitted_at?->translatedFormat('j F Y') }}</td>
                                <td data-label="{{ __('common.action') }}">
                                    <form method="POST" action="{{ route('admin.assignments.release', $affectation) }}">
                                        @csrf
                                        <x-button type="submit" variant="secondary">{{ __('admin.assignments.release') }}</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card>
        <h2 class="card__title">{{ __('admin.assignments.current_title') }}</h2>

        @if ($actives->isEmpty())
            <x-empty-state :level="3" :title="__('admin.assignments.current_none_title')">
                {{ __('admin.assignments.current_none_body') }}
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.assignments.current_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.assignments.current_aria') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">{{ __('common.centre') }}</th>
                            <th scope="col">{{ __('admin.assignments.assigned_agent') }}</th>
                            <th scope="col">{{ __('common.submitted_on') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($actives as $affectation)
                            <tr>
                                <td data-label="{{ __('common.reference') }}">{{ $affectation->reference }}</td>
                                <td data-label="{{ __('common.centre') }}">{{ $affectation->center?->name }}</td>
                                <td data-label="{{ __('admin.assignments.assigned_agent') }}">{{ $affectation->assignedOfficer?->name }}</td>
                                <td data-label="{{ __('common.submitted_on') }}">{{ $affectation->submitted_at?->translatedFormat('j F Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
