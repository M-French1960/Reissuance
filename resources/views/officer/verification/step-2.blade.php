@extends('officer.verification._layout')
@section('etape')
    @php $etape = $etapes->get(2); $charge = $etape?->payload ?? []; @endphp

    <x-card :title="__('verification.steps.2')">
        <p class="u-note">{{ __('officer.step2.intro') }}</p>
        @include('officer.verification.result', ['etape' => $etape])

        @if ($etape?->result === \App\Enums\VerificationResult::ProviderUnavailable)
            <x-alert variant="attention" :title="__('officer.step2.unavailable_title')">
                {{ $charge['message'] ?? __('officer.step2.no_answer') }}
                {{ __('officer.step2.unavailable_body') }}
            </x-alert>
        @endif

        @if ($charge !== [])
            <dl class="review">
                <div class="review__row"><dt>{{ __('officer.step2.result') }}</dt><dd>{{ $charge['message'] ?? '' }}</dd></div>
                @if (isset($charge['payload']['name_on_document']))
                    <div class="review__row"><dt>{{ __('officer.step2.name_on_document') }}</dt><dd>{{ $charge['payload']['name_on_document'] }}</dd></div>
                @endif
                @if (isset($charge['payload']['document_status']))
                    <div class="review__row"><dt>{{ __('officer.step2.document_state') }}</dt><dd>{{ $charge['payload']['document_status'] }}</dd></div>
                @endif
                <div class="review__row"><dt>{{ __('officer.step2.check_reference') }}</dt><dd>{{ $charge['correlation_id'] ?? '' }}</dd></div>
                <div class="review__row"><dt>{{ __('officer.step2.service_queried') }}</dt><dd>{{ $charge['provider'] ?? '' }}</dd></div>
            </dl>
        @endif

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.identity', $demande) }}">
                @csrf
                <x-button type="submit" variant="primary">
                    {{ $etape ? __('officer.step2.run_check_again') : __('officer.step2.run_check') }}
                </x-button>
            </form>
        @endif
    </x-card>
@endsection
