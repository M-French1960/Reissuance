@extends('citizen.wizard._layout')

@section('wizard')
    @php
        $selfie = $draft->attachments->firstWhere('kind', 'selfie');
        $piece = $draft->attachments->firstWhere('kind', 'id_document');
        $complet = $selfie && $piece;
    @endphp

    <x-card :title="__('wizard.step4.check_title')">
        <p>{{ __('wizard.step4.check_intro') }}</p>

        <h3>{{ __('wizard.step4.your_request') }}</h3>
        <dl class="review">
            <div class="review__row"><dt>{{ __('wizard.step4.reason') }}</dt><dd>{{ $draft->reason === 'lost' ? __('wizard.step4.reason_lost') : __('wizard.step4.reason_damaged') }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step4.copies') }}</dt><dd>{{ $draft->copies_requested }}</dd></div>
            <div class="review__row"><dt>{{ __('common.centre') }}</dt><dd>{{ $draft->center?->situation() }}</dd></div>
        </dl>

        <h3>{{ __('wizard.step4.wanted_certificate') }}</h3>
        <dl class="review">
            <div class="review__row"><dt>{{ __('wizard.step4.name_at_birth') }}</dt><dd>{{ $draft->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step2.date_of_birth') }}</dt><dd>{{ $draft->date_of_birth?->translatedFormat('d F Y') }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step4.place') }}</dt><dd>{{ $draft->place_of_birth }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step2.registration_year') }}</dt><dd>{{ $draft->registration_year }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step4.original_number') }}</dt><dd>{{ $draft->original_certificate_number ?: __('common.not_provided') }}</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step4.father') }}</dt><dd>{{ $draft->father_name }} ({{ $draft->father_nationality }})</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step4.mother') }}</dt><dd>{{ $draft->mother_name }} ({{ $draft->mother_nationality }})</dd></div>
            <div class="review__row"><dt>{{ __('wizard.step4.parents_address') }}</dt><dd>{{ $draft->parents_address }}</dd></div>
        </dl>

        <h3>{{ __('wizard.step4.documents') }}</h3>
        <dl class="review">
            <div class="review__row">
                <dt>{{ __('wizard.step4.your_photo') }}</dt>
                <dd>@if ($selfie)<span class="badge badge--success">{{ __('wizard.step3.saved') }}</span>@else<span class="badge badge--danger">{{ __('wizard.step4.missing') }}</span>@endif</dd>
            </div>
            <div class="review__row">
                <dt>{{ __('wizard.step4.id_document') }}</dt>
                <dd>@if ($piece)<span class="badge badge--success">{{ __('wizard.step3.saved') }}</span>@else<span class="badge badge--danger">{{ __('wizard.step4.missing') }}</span>@endif</dd>
            </div>
        </dl>

        <p><a href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3]) }}">{{ __('wizard.step4.fix_centre_or_photos') }}</a></p>
    </x-card>

    @unless ($complet)
        <x-alert variant="danger" :title="__('wizard.step4.missing_title')"
                 :action="route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3])"
                 :action-label="__('wizard.step4.add_missing_photo')">
            {{ __('wizard.step4.missing_body') }}
        </x-alert>
    @endunless

    <x-card :title="__('wizard.step4.sending')">
        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]) }}">
            @csrf
            <p>{{ __('wizard.step4.confirm_body') }}</p>

            <div class="actions">
                <x-button href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3]) }}" variant="secondary">{{ __('common.back') }}</x-button>
                <x-button type="submit" variant="primary" :disabled="! $complet">{{ __('wizard.step4.send') }}</x-button>
            </div>
        </form>
    </x-card>
@endsection
