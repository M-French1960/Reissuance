@extends('citizen.wizard._layout')

@section('wizard')
    <x-card :title="__('wizard.step2.title')">
        <p>{{ __('wizard.step2.intro') }}</p>

        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 2]) }}">
            @csrf

            <x-field name="full_name_at_birth" :label="__('wizard.step2.full_name_at_birth')" required
                     :value="$draft->full_name_at_birth"
                     :hint="__('wizard.step2.full_name_hint')"
                     :error="$errors->first('full_name_at_birth')" />

            <div class="grid grid--2">
                <x-field name="date_of_birth" :label="__('wizard.step2.date_of_birth')" type="date" required
                         :value="$draft->date_of_birth?->format('Y-m-d')"
                         :error="$errors->first('date_of_birth')" />
                <x-field name="registration_year" :label="__('wizard.step2.registration_year')" type="number" required
                         :value="$draft->registration_year" min="1900" max="{{ date('Y') }}"
                         :hint="__('wizard.step2.registration_year_hint')"
                         :error="$errors->first('registration_year')" />
            </div>

            <x-field name="place_of_birth" :label="__('wizard.step2.place_of_birth')" required
                     :value="$draft->place_of_birth"
                     :hint="__('wizard.step2.place_of_birth_hint')"
                     :error="$errors->first('place_of_birth')" />

            <x-field name="original_certificate_number" :label="__('wizard.step2.original_number')"
                     :value="$draft->original_certificate_number"
                     :hint="__('wizard.step2.original_number_hint')"
                     :error="$errors->first('original_certificate_number')" />

            <h3 class="divider-title">{{ __('wizard.step2.parents') }}</h3>

            <div class="grid grid--2">
                <x-field name="father_name" :label="__('wizard.step2.father_name')" required
                         :value="$draft->father_name" :error="$errors->first('father_name')" />
                <x-field name="mother_name" :label="__('wizard.step2.mother_name')" required
                         :value="$draft->mother_name" :error="$errors->first('mother_name')" />
            </div>

            <div class="grid grid--2">
                <x-field name="father_nationality" :label="__('wizard.step2.father_nationality')" required
                         :value="$draft->father_nationality ?? __('wizard.step2.default_nationality')"
                         :error="$errors->first('father_nationality')" />
                <x-field name="mother_nationality" :label="__('wizard.step2.mother_nationality')" required
                         :value="$draft->mother_nationality ?? __('wizard.step2.default_nationality')"
                         :error="$errors->first('mother_nationality')" />
            </div>

            <x-field name="parents_address" :label="__('wizard.step2.parents_address')" required
                     :value="$draft->parents_address" :error="$errors->first('parents_address')" />

            <div class="actions">
                <x-button href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 1]) }}" variant="secondary">{{ __('common.back') }}</x-button>
                <x-button type="submit" variant="primary">{{ __('common.continue') }}</x-button>
            </div>
        </form>
    </x-card>
@endsection
