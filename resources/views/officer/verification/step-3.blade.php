@extends('officer.verification._layout')
@section('etape')
    @php
        $selfie = $demande->attachments->firstWhere('kind', 'selfie');
        $piece = $demande->attachments->firstWhere('kind', 'id_document');
    @endphp

    <x-card :title="__('verification.steps.3')">
        <p class="u-note">{{ __('officer.step3.intro') }}</p>

        @include('officer.verification.result', ['etape' => $etapes->get(3)])

        <div class="compare">
            @foreach ([[__('officer.step3.selfie'), $selfie], [__('officer.step3.id_document'), $piece]] as [$titre, $piece_])
                <figure class="compare__pane">
                    <figcaption>{{ $titre }}</figcaption>
                    @if ($piece_)
                        <a href="{{ route('citizen.attachments.show', $piece_) }}" target="_blank" rel="noopener">
                            <img class="compare__image" src="{{ route('citizen.attachments.show', $piece_) }}"
                                 alt="{{ __('officer.step3.open_larger', ['title' => $titre]) }}">
                        </a>
                        <p class="u-note">{{ __('officer.step3.taken_on', [
                            'size' => round($piece_->size_bytes / 1024),
                            'date' => $piece_->captured_at?->translatedFormat('d/m/Y H:i'),
                        ]) }}</p>
                    @else
                        <p class="badge badge--danger">{{ __('officer.step3.missing_document') }}</p>
                    @endif
                </figure>
            @endforeach
        </div>

        {{-- Facial comparison: compulsory before concluding, and separate from
             the decision. The machine gives an opinion, the officer decides. --}}
        @if ($peutDecider)
            <div class="u-stack">
                @if ($avisFacial === null)
                    <x-alert variant="attention" :title="__('officer.step3.to_run_title')">
                        {!! __('officer.step3.to_run_body', ['strong' => '<strong>'.e(__('officer.step3.to_run_strong')).'</strong>']) !!}
                    </x-alert>
                @else
                    @php
                        $issue = \App\Support\ProviderOutcome::from($avisFacial['outcome']);
                        $score = $avisFacial['payload']['similarity'] ?? null;
                    @endphp
                    <x-alert :variant="$issue === \App\Support\ProviderOutcome::Match ? 'success' : 'attention'"
                             :title="__('officer.step3.opinion_title')">
                        <p class="u-flush">
                            <span class="badge badge--{{ $issue->toVerificationResult()->tone() }}">{{ $issue->label() }}</span>
                            @if ($score !== null)
                                <span class="u-note">{{ __('officer.step3.similarity', ['score' => number_format((float) $score * 100, 0)]) }}</span>
                            @endif
                        </p>
                        <p>{{ $avisFacial['message'] ?? '' }}</p>
                        <p class="u-note">{{ __('officer.step3.opinion_note') }}</p>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('officer.verification.facial', $demande) }}">
                    @csrf
                    <x-button type="submit" variant="secondary">
                        {{ $avisFacial ? __('officer.step3.run_again') : __('officer.step3.run') }}
                    </x-button>
                </form>
            </div>

            <form method="POST" action="{{ route('officer.verification.acknowledge', ['reissuanceRequest' => $demande, 'step' => 3]) }}">
                @csrf
                <fieldset class="fieldset">
                    <legend class="field__label">{{ __('officer.step3.question') }} <span aria-hidden="true">*</span></legend>
                    @foreach ([
                        'match' => __('officer.step3.answer_match'),
                        'inconclusive' => __('officer.step3.answer_inconclusive'),
                        'no_match' => __('officer.step3.answer_no_match'),
                    ] as $valeur => $libelle)
                        <div class="field--inline">
                            <input type="radio" id="r3-{{ $valeur }}" name="result" value="{{ $valeur }}" class="field__checkbox" required>
                            <label for="r3-{{ $valeur }}">{{ $libelle }}</label>
                        </div>
                    @endforeach
                </fieldset>
                <x-field name="note" :label="__('officer.step1.observation')" :hint="__('officer.step3.observation_hint')" />
                <x-button type="submit" variant="primary">{{ __('officer.step1.save_and_continue') }}</x-button>
            </form>
        @endif
    </x-card>
@endsection
