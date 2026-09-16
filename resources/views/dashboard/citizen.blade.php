@extends('layouts.app')
@section('title', __('dashboard.citizen.title'))

@section('content')
    {{--
        THE SCREEN A CITIZEN LANDS ON AFTER SIGNING IN (D-073).

        It stayed a milestone 2 sketch: its empty state announced "submitting a
        request will be available at the next milestone", telling the citizen
        they could not do the one thing they came for. And it offered no
        action at all, not even a link.
    --}}
    <h1>{{ __('dashboard.citizen.heading') }}</h1>
    <p>{{ __('dashboard.citizen.lede') }}</p>

    <x-flash />

    @unless ($profilComplet)
        {{-- Said before they run into it: submitting needs a complete profile. --}}
        <x-alert variant="attention" title="{{ __('dashboard.citizen.profile_incomplete_title') }}">
            <p>{{ __('dashboard.citizen.profile_incomplete_body') }}</p>
            <div class="row-actions">
                <x-button href="{{ route('citizen.profile.edit') }}" variant="primary">{{ __('dashboard.citizen.complete_profile') }}</x-button>
            </div>
        </x-alert>
    @endunless

    <x-card :title="__('dashboard.citizen.requests_title')">
        @if ($requests->isEmpty())
            <x-empty-state :title="__('dashboard.citizen.empty_title')">
                <p>{{ __('dashboard.citizen.empty_body') }}</p>
                <form method="POST" action="{{ route('citizen.requests.start') }}">
                    @csrf
                    <x-button type="submit" variant="primary">{{ __('dashboard.citizen.apply') }}</x-button>
                </form>
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('dashboard.citizen.requests_title') }}">
                <table>
                    <caption class="visually-hidden">{{ __('dashboard.citizen.requests_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">{{ __('common.status') }}</th>
                            <th scope="col">{{ __('common.submitted_on') }}</th>
                            <th scope="col">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr>
                                <td>{{ $r->reference }}</td>
                                <td><x-status-badge :status="$r->status" /></td>
                                <td>{{ $r->submitted_at?->translatedFormat('d/m/Y') ?? '' }}</td>
                                <td>
                                    {{-- A table with no action is a dead end: this one led
                                         here without ever letting a request be opened. --}}
                                    @if ($r->status === \App\Enums\RequestStatus::Draft)
                                        <a href="{{ route('citizen.requests.step', [$r, $r->last_completed_step ? min($r->last_completed_step + 1, 4) : 1]) }}">{{ __('common.resume') }}</a>
                                    @else
                                        <a href="{{ route('citizen.requests.show', $r) }}">{{ __('common.track') }}</a>
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
                    <x-button type="submit" variant="primary">{{ __('dashboard.citizen.apply_again') }}</x-button>
                </form>
                <x-button href="{{ route('citizen.requests.index') }}" variant="secondary">{{ __('dashboard.citizen.all_requests') }}</x-button>
            </div>
        @endif
    </x-card>
@endsection
