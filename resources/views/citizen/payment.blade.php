@extends('layouts.app')
@section('title', __('payment.title'))

@section('content')
    <h1>{{ __('payment.title') }}</h1>
    <p class="u-note">{{ __('payment.request_line', ['reference' => $demande->reference]) }}</p>

    <x-flash />

    @if ($errors->any())
        <x-alert variant="danger" :title="__('payment.failed_title')">
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </x-alert>
    @endif

    <x-card :title="__('payment.amount_title')">
        <dl class="review">
            <div class="review__row">
                <dt>{{ __('payment.amount') }}</dt>
                <dd><strong>{{ $montant?->format() }}</strong></dd>
            </div>
            @if ($baseLegale)
                <div class="review__row">
                    <dt>{{ __('payment.legal_basis') }}</dt>
                    <dd>{{ $baseLegale }}</dd>
                </div>
            @endif
        </dl>

        @unless ($baseLegale)
            {{-- No legal basis is invented (§10 of the brief). While none is
                 configured, the screen says it is not showing one. --}}
            <p class="u-note">{{ __('payment.legal_basis_missing') }}</p>
        @endunless

        <p>{{ $avantEnvoi ? __('payment.after_payment_before_send') : __('payment.after_payment_before_sign') }}</p>
    </x-card>

    @if ($paiement && $paiement->isPaid())
        <x-card :title="__('payment.confirmed_title')">
            <p><span class="badge badge--{{ $paiement->status->tone() }}">{{ $paiement->status->label() }}</span></p>
            <dl class="review">
                <div class="review__row"><dt>{{ __('payment.amount') }}</dt><dd>{{ $paiement->money()->format() }}</dd></div>
                <div class="review__row"><dt>{{ __('payment.date') }}</dt><dd>{{ $paiement->settled_at?->translatedFormat('d/m/Y H:i') }}</dd></div>
                <div class="review__row"><dt>{{ __('payment.method') }}</dt><dd>{{ $paiement->operator?->label() }}</dd></div>
                <div class="review__row"><dt>{{ __('common.reference') }}</dt><dd>{{ $paiement->provider_reference }}</dd></div>
            </dl>
            <x-button :href="route('citizen.requests.payment.receipt', $demande)" variant="secondary">
                {{ __('payment.download_receipt') }}
            </x-button>
        </x-card>
    @elseif ($paiement)
        <x-card :title="__('payment.in_progress_title')">
            <p><span class="badge badge--{{ $paiement->status->tone() }}">{{ $paiement->status->label() }}</span></p>

            @if ($paiement->status === \App\Enums\PaymentStatus::Authorised)
                <x-alert variant="attention" :title="__('payment.not_settled_title')">
                    {{ __('payment.not_settled_body') }}
                </x-alert>
            @endif

            <form method="POST" action="{{ route('citizen.requests.payment.reconcile', $demande) }}">
                @csrf
                <x-button type="submit" variant="secondary">{{ __('payment.refresh') }}</x-button>
            </form>
        </x-card>
    @endif

    @if (! $paiement || ! $paiement->isPaid())
        <x-card :title="__('payment.pay_title')">
            <form method="POST" action="{{ route('citizen.requests.payment.store', $demande) }}">
                @csrf

                <fieldset class="fieldset">
                    <legend class="field__label">
                        {{ __('payment.how_to_pay') }} <span aria-hidden="true">*</span>
                    </legend>
                    @foreach ($operateurs as $operateur)
                        <div class="field--inline">
                            <input type="radio" class="field__checkbox" required
                                   id="op-{{ $operateur->value }}" name="operator"
                                   value="{{ $operateur->value }}"
                                   @checked(old('operator', $paiement?->operator?->value) === $operateur->value)>
                            <label for="op-{{ $operateur->value }}">
                                {{ $operateur->label() }}
                                <span class="u-note">{{ $operateur->hint() }}</span>
                            </label>
                        </div>
                    @endforeach
                    @if ($errors->has('operator'))
                        <p class="field__error">{{ $errors->first('operator') }}</p>
                    @endif
                </fieldset>

                <div class="field">
                    <label class="field__label" for="payer_reference">
                        {{ __('payment.payer_number') }} <span aria-hidden="true">*</span>
                        <span class="field__hint">{{ __('payment.payer_number_hint') }}</span>
                    </label>
                    <input class="field__control" id="payer_reference" name="payer_reference"
                           type="tel" inputmode="tel" autocomplete="tel" required
                           value="{{ old('payer_reference', $paiement?->payer_reference) }}"
                           @if ($errors->has('payer_reference')) aria-invalid="true" @endif>
                    @if ($errors->has('payer_reference'))
                        <p class="field__error">{{ $errors->first('payer_reference') }}</p>
                    @endif
                </div>

                <x-button type="submit" variant="primary">
                    {{ __('payment.pay_amount', ['amount' => $montant?->format()]) }}
                </x-button>
            </form>
        </x-card>
    @endif

    <p class="u-note">
        <a href="{{ route('citizen.requests.show', $demande) }}">{{ __('payment.back_to_request') }}</a>
    </p>
@endsection
