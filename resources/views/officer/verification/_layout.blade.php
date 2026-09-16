@extends('layouts.app')
@section('title', $steps[$step])

@section('content')
    <h1>{{ __('verification.heading', ['reference' => $demande->reference]) }}</h1>
    <p class="u-note">
        {{ __('verification.meta', [
            'name' => $demande->full_name_at_birth ?? '',
            'status' => $demande->status->label(),
        ]) }}
        @if ($demande->verification_cycle > 1)
            <strong>{{ __('verification.pass_number', ['number' => $demande->verification_cycle]) }}</strong>
        @endif
    </p>

    {{-- The steps ACTUALLY recorded, not the ones we walked past: verification
         is freely navigable, so position proves nothing (D-074). --}}
    <x-step-indicator :steps="$steps" :current="$step"
                      :done="$etapes->filter(fn ($e) => $e->result !== null)->keys()->all()" />

    <x-flash />

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">{{ __('officer.action_impossible') }}</p>
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    {{--
        Three distinct reasons for not being able to decide, and three distinct
        messages. The earlier version folded everything into "taken on by
        another agent", including when NOBODY had taken it: it stated something
        false and hid the only possible action (D-055).
    --}}
    @unless ($peutDecider)
        @if ($peutPrendreEnCharge)
            <x-alert variant="attention" :title="__('verification.claim_title')">
                {{ __('verification.claim_body') }}
                <form method="POST" action="{{ route('officer.verification.claim', $demande) }}" class="alert__action">
                    @csrf
                    <x-button type="submit">{{ __('verification.claim_action') }}</x-button>
                </form>
            </x-alert>
        @elseif ($demande->assignedOfficer !== null)
            <x-alert variant="attention" :title="__('verification.read_only_title')">
                {{ __('verification.read_only_body', ['name' => $demande->assignedOfficer->name]) }}
            </x-alert>
        @else
            <x-alert variant="attention" :title="__('verification.unassigned_title')">
                {{ __('verification.unassigned_body', ['status' => $demande->status->label()]) }}
            </x-alert>
        @endif
    @endunless

    @yield('etape')

    <p class="u-note">{!! __('verification.shortcuts', [
        'next' => '<kbd>&rarr;</kbd>',
        'previous' => '<kbd>&larr;</kbd>',
    ]) !!}</p>

    <nav class="actions" aria-label="{{ __('verification.step_navigation') }}" data-step-nav>
        @if ($step > 1)
            <x-button href="{{ route('officer.verification.step', ['reissuanceRequest' => $demande, 'step' => $step - 1]) }}" variant="secondary" data-step-prev>{{ __('verification.previous_step') }}</x-button>
        @else
            <x-button href="{{ route('officer.queue') }}" variant="secondary">{{ __('verification.back_to_queue') }}</x-button>
        @endif
        @if ($step < 5)
            <x-button href="{{ route('officer.verification.step', ['reissuanceRequest' => $demande, 'step' => $step + 1]) }}" variant="secondary" data-step-next>{{ __('verification.next_step') }}</x-button>
        @endif
    </nav>
@endsection
