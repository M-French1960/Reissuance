@extends('layouts.app')
@section('title', __('dashboard.officer.title'))

@section('content')
    {{--
        THE SCREEN AN OFFICER LANDS ON AFTER SIGNING IN (D-073).

        It stayed a milestone 2 sketch: three counters, then "Milestone 2, the
        processing queue and the five-step verification arrive at milestone 4".
        Both had existed for a long time. And none of the counters led to the
        queue: the agent read a number with no way to open it.
    --}}
    <h1>{{ __('dashboard.officer.heading') }}</h1>
    <p>{{ __('dashboard.officer.centre_line', ['centre' => auth()->user()->center?->name ?? __('common.none')]) }}</p>

    <x-flash />

    {{-- Each counter OPENS the queue filtered on its status. --}}
    <div class="grid grid--3">
        @foreach ([\App\Enums\RequestStatus::Pending, \App\Enums\RequestStatus::UnderReview, \App\Enums\RequestStatus::AwaitingSignature] as $status)
            <x-card>
                <p class="stat__label">{{ $status->label() }}</p>
                <p class="stat__value">{{ $counts[$status->value] }}</p>
                {{-- The query parameter is `statut`, not `status`. --}}
                <x-button href="{{ route('officer.queue', ['statut' => $status->value]) }}" variant="secondary">
                    {{ __('common.open') }}
                </x-button>
            </x-card>
        @endforeach
    </div>

    <x-card :title="__('dashboard.officer.queue_title')">
        <p>{{ __('dashboard.officer.queue_body') }}</p>
        <div class="row-actions">
            <x-button href="{{ route('officer.queue') }}" variant="primary">{{ __('dashboard.officer.open_queue') }}</x-button>
            <x-button href="{{ route('notifications.index') }}" variant="secondary">{{ __('common.notifications') }}</x-button>
        </div>
    </x-card>
@endsection
