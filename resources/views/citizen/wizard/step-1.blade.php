@extends('citizen.wizard._layout')

@section('wizard')
    <x-card :title="__('wizard.step1.your_details')">
        <p>{{ __('wizard.step1.from_profile') }}</p>

        <dl class="review">
            <div class="review__row"><dt>{{ __('common.name') }}</dt><dd>{{ $profile->fullName() }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step1.date_of_birth') }}</dt><dd>{{ $profile->birth_date?->translatedFormat('d F Y') }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step1.place_of_birth') }}</dt><dd>{{ $profile->birth_place }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step1.phone') }}</dt><dd>{{ $profile->phone }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step1.address') }}</dt><dd>{{ $profile->address }}</dd></div>
        </dl>

        <p><a href="{{ route('citizen.profile.edit') }}">{{ __('wizard.step1.fix_profile') }}</a></p>
    </x-card>

    <x-card :title="__('wizard.step1.reason_title')">
        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]) }}">
            @csrf

            <fieldset class="fieldset">
                <legend class="field__label">{{ __('wizard.step1.reason_legend') }} <span aria-hidden="true">*</span></legend>
                @foreach (['lost' => __('wizard.step1.reason_lost'), 'damaged' => __('wizard.step1.reason_damaged')] as $value => $label)
                    <div class="field--inline">
                        <input type="radio" id="reason-{{ $value }}" name="reason" value="{{ $value }}"
                               class="field__checkbox" required
                               @checked(old('reason', $draft->reason) === $value)>
                        <label for="reason-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </fieldset>

            <x-field name="copies_requested" :label="__('wizard.step1.copies')" type="number"
                     :value="$draft->copies_requested ?? 1" required min="1" max="10"
                     :hint="__('wizard.step1.copies_hint')" :error="$errors->first('copies_requested')" />

            <x-button type="submit" variant="primary">{{ __('common.continue') }}</x-button>
        </form>
    </x-card>
@endsection
