@extends('citizen.wizard._layout')

@section('wizard')
    @php
        $selfie = $draft->attachments->firstWhere('kind', 'selfie');
        $piece = $draft->attachments->firstWhere('kind', 'id_document');
    @endphp

    <div class="alert alert--danger" role="alert" data-offline-banner hidden>
        <p class="alert__title">{{ __('wizard.step3.offline_title') }}</p>
        {{ __('wizard.step3.offline_body') }}
    </div>

    <x-card :title="__('wizard.step3.centre_title')">
        <p>{{ __('wizard.step3.centre_intro') }}</p>

        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 3]) }}" id="centre-form">
            @csrf
            <div class="field">
                <label class="field__label" for="civil_status_center_id">
                    {{ __('wizard.step3.centre_title') }} <span aria-hidden="true">*</span>
                </label>
                <select class="field__control" id="civil_status_center_id" name="civil_status_center_id" required
                        @if ($errors->has('civil_status_center_id')) aria-invalid="true" @endif>
                    <option value="">{{ __('wizard.step3.centre_choose') }}</option>
                    @foreach ($centers as $center)
                        <option value="{{ $center->id }}" @selected(old('civil_status_center_id', $draft->civil_status_center_id) == $center->id)>
                            {{ $center->situation() }}
                        </option>
                    @endforeach
                </select>
                @if ($errors->has('civil_status_center_id'))
                    <p class="field__error">{{ $errors->first('civil_status_center_id') }}</p>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :title="__('wizard.step3.documents_title')">
        <p>{{ __('wizard.step3.documents_intro') }}</p>

        @if ($errors->has('file') || $errors->has('attachments'))
            <x-alert variant="danger" :title="__('wizard.step3.upload_failed')">
                {{ $errors->first('file') ?: $errors->first('attachments') }}
            </x-alert>
        @endif

        {{-- Notice on the biometric processing. The match is compulsory
             (D-045), so there is no box to tick, but the person has to know
             what will be done with their photograph, and that the decision
             stays a human one. --}}
        <x-alert variant="attention" :title="__('wizard.step3.comparison_title')">
            <p>{!! __('wizard.step3.comparison_body', ['strong' => '<strong>'.e(__('wizard.step3.comparison_strong')).'</strong>']) !!}</p>
            <p class="u-flush">{!! __('wizard.step3.comparison_failure', ['strong' => '<strong>'.e(__('wizard.step3.comparison_failure_strong')).'</strong>']) !!}</p>
        </x-alert>

        <div class="grid grid--2">
            @foreach ([
                ['selfie', __('wizard.step3.selfie_title'), $selfie, __('wizard.step3.selfie_help'), 'user'],
                ['id_document', __('wizard.step3.id_title'), $piece, __('wizard.step3.id_help'), 'environment'],
            ] as [$kind, $titre, $existant, $aide, $facing])
                <div class="capture" data-capture data-capture-noscript>
                    <h3>{{ $titre }}</h3>
                    <p class="field__hint">{{ $aide }}</p>

                    <form method="POST"
                          action="{{ route('citizen.requests.attachments.store', $draft) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="kind" value="{{ $kind }}">

                        <img class="capture__preview" data-capture-preview alt="" hidden>

                        @if ($existant)
                            <p class="capture__state">
                                <span class="badge badge--success">{{ __('wizard.step3.saved') }}</span>
                                <span class="field__hint">{{ __('wizard.step3.size_and_date', [
                                    'size' => round($existant->size_bytes / 1024),
                                    'date' => $existant->captured_at?->translatedFormat('d/m/Y H:i'),
                                ]) }}</span>
                            </p>
                        @endif

                        <div class="field">
                            <label class="field__label" for="file-{{ $kind }}">
                                {{ $existant ? __('wizard.step3.retake_photo') : __('wizard.step3.take_photo') }}
                            </label>
                            <input class="field__control" type="file" id="file-{{ $kind }}" name="file"
                                   accept="image/jpeg,image/png,image/webp"
                                   capture="{{ $facing }}"
                                   data-capture-input required>
                            <span class="field__hint" data-capture-feedback>{{ __('wizard.step3.compression_note') }}</span>
                        </div>

                        <x-button type="submit" variant="secondary" data-capture-submit>
                            {{ $existant ? __('wizard.step3.replace') : __('wizard.step3.send_photo') }}
                        </x-button>
                    </form>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="actions">
        <x-button href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 2]) }}" variant="secondary">{{ __('common.back') }}</x-button>
        <x-button type="submit" variant="primary" form="centre-form">{{ __('common.continue') }}</x-button>
    </div>
@endsection
