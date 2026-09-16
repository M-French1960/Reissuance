@extends('layouts.app')
@section('title', __('dashboard.mayor.title'))

@section('content')
    {{--
        THE SCREEN A MAYOR LANDS ON AFTER SIGNING IN (D-073).

        It stayed a milestone 2 sketch: two counters, then "Milestone 2, both
        queues and the electronic signature arrive at milestone 5". Both exist.
        And no counter led to the signing queue.
    --}}
    <h1>{{ __('dashboard.mayor.heading') }}</h1>
    <p>{{ __('dashboard.mayor.commune_line', ['commune' => auth()->user()->commune?->name ?? __('common.none')]) }}</p>

    <x-flash />

    <div class="grid grid--2">
        @foreach ([\App\Enums\RequestStatus::AwaitingSignature, \App\Enums\RequestStatus::Escalated] as $status)
            <x-card>
                <p class="stat__label">{{ $status->label() }}</p>
                <p class="stat__value">{{ $counts[$status->value] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card :title="__('dashboard.mayor.sign_title')">
        <p>{!! __('dashboard.mayor.sign_body', ['strong' => '<strong>'.e(__('dashboard.mayor.sign_body_strong')).'</strong>']) !!}</p>
        <div class="row-actions">
            <x-button href="{{ route('mayor.dashboard') }}" variant="primary">{{ __('dashboard.mayor.open_signing_queue') }}</x-button>
            <x-button href="{{ route('two-factor.setup') }}" variant="secondary">{{ __('dashboard.mayor.enrol_device') }}</x-button>
        </div>
    </x-card>
@endsection
