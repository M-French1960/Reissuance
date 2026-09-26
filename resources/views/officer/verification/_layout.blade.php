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

    {{--
        RECLAMER UNE PIECE AU DEMANDEUR (D-087).

        Disponible a toutes les etapes : le defaut se voit a l'etape 2 pour la
        piece d'identite, a l'etape 3 pour le portrait. Avant, une photo floue
        ne laissait qu'une issue, le rejet d'une personne de bonne foi.
    --}}
    @php $complementEnAttente = $demande->pendingComplement; @endphp

    @if ($complementEnAttente !== null)
        <x-alert variant="attention" :title="__('officer.complement.pending_title')">
            <p class="u-flush">{{ __('officer.complement.pending_body', [
                'piece' => __('officer.complement.piece_'.$complementEnAttente->kind),
                'date' => $complementEnAttente->created_at->translatedFormat('d F Y'),
            ]) }}</p>
            <blockquote class="quote">{{ $complementEnAttente->message }}</blockquote>
        </x-alert>
    @elseif ($peutDecider)
        <x-card :title="__('officer.complement.ask_title')">
            <p class="u-note">{{ __('officer.complement.ask_intro') }}</p>

            <form method="POST" action="{{ route('officer.complement.store', $demande) }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="complement-kind">{{ __('officer.complement.which') }}</label>
                    <select class="field__control" id="complement-kind" name="kind">
                        <option value="id_document">{{ __('officer.complement.piece_id_document') }}</option>
                        <option value="selfie">{{ __('officer.complement.piece_selfie') }}</option>
                    </select>
                </div>

                <div class="field">
                    <label class="field__label" for="complement-message">{{ __('officer.complement.message') }}</label>
                    {{-- Le motif est ce que le demandeur lira : s'il ne dit pas
                         QUOI refaire, la meme photo revient. --}}
                    <span class="field__hint" id="complement-message-hint">{{ __('officer.complement.message_hint') }}</span>
                    <textarea class="field__control" id="complement-message" name="message" rows="3"
                              minlength="10" maxlength="1000"
                              aria-describedby="complement-message-hint">{{ old('message') }}</textarea>
                </div>

                <x-button type="submit" variant="secondary">{{ __('officer.complement.ask_action') }}</x-button>
            </form>
        </x-card>
    @endif

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
