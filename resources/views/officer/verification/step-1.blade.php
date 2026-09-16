@extends('officer.verification._layout')
@section('etape')
    <x-card :title="__('verification.steps.1')">
        @include('officer.verification.result', ['etape' => $etapes->get(1)])

        <h3>{{ __('officer.step1.applicant') }}</h3>
        <dl class="review">
            <div class="review__row"><dt>{{ __('officer.step1.account_name') }}</dt><dd>{{ $demande->citizen->name }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.profile_name') }}</dt><dd>{{ $demande->citizen->profile?->fullName() }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.date_of_birth') }}</dt><dd>{{ $demande->citizen->profile?->birth_date?->translatedFormat('d F Y') }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.phone') }}</dt><dd>{{ $demande->citizen->profile?->phone }}</dd></div>
        </dl>

        <h3>{{ __('officer.step1.requested_certificate') }}</h3>
        <dl class="review">
            <div class="review__row"><dt>{{ __('officer.step1.reason') }}</dt><dd>{{ $demande->reason === 'lost' ? __('officer.step1.reason_lost') : __('officer.step1.reason_damaged') }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.name_at_birth') }}</dt><dd>{{ $demande->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.date_of_birth') }}</dt><dd>{{ $demande->date_of_birth?->translatedFormat('d F Y') }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.place') }}</dt><dd>{{ $demande->place_of_birth }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.registration_year') }}</dt><dd>{{ $demande->registration_year }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.original_number') }}</dt><dd>{{ $demande->original_certificate_number ?: __('common.not_provided') }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.father') }}</dt><dd>{{ $demande->father_name }} ({{ $demande->father_nationality }})</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.mother') }}</dt><dd>{{ $demande->mother_name }} ({{ $demande->mother_nationality }})</dd></div>
        </dl>

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.acknowledge', ['reissuanceRequest' => $demande, 'step' => 1]) }}">
                @csrf
                <fieldset class="fieldset">
                    <legend class="field__label">{{ __('officer.step1.question') }} <span aria-hidden="true">*</span></legend>
                    @foreach ([
                        'match' => __('officer.step1.answer_match'),
                        'inconclusive' => __('officer.step1.answer_inconclusive'),
                        'no_match' => __('officer.step1.answer_no_match'),
                    ] as $valeur => $libelle)
                        <div class="field--inline">
                            <input type="radio" id="r1-{{ $valeur }}" name="result" value="{{ $valeur }}" class="field__checkbox" required>
                            <label for="r1-{{ $valeur }}">{{ $libelle }}</label>
                        </div>
                    @endforeach
                </fieldset>
                <x-field name="note" :label="__('officer.step1.observation')" :hint="__('officer.step1.observation_hint')" />
                <x-button type="submit" variant="primary">{{ __('officer.step1.save_and_continue') }}</x-button>
            </form>
        @endif
    </x-card>
@endsection
