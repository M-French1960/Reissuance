@extends('layouts.app')
@section('title', __('citizen.tracking.title'))

@section('content')
    <h1>{{ __('citizen.tracking.heading', ['reference' => $demande->reference]) }}</h1>

    <x-flash />

    <x-card>
        <p>
            <x-status-badge :status="$demande->status" />
            @if ($demande->submitted_at)
                <span class="field__hint">{{ __('common.submitted_on') }} {{ $demande->submitted_at->translatedFormat('d F Y') }}</span>
            @endif
        </p>
        <p class="field__hint">
            {{-- The centre's name already carries "Civil status centre of ..."
                 and, most of the time, the name of the commune (D-075). --}}
            {{ __('citizen.tracking.issued_by', ['centre' => $demande->center?->situation() ?? __('common.none')]) }}
        </p>
    </x-card>

    @if ($refus !== null)
        {{-- THE REASON FOR THE REJECTION, SAID (D-075).

             The page showed "Rejected" and nothing else. The agent who enters
             this reason reads "It will be visible in the file", and the
             notifications page points here promising "the full detail of a
             request, including the reason for a rejection". It pointed at a
             page that said nothing.

             Placed BEFORE the timeline: this is what the applicant came to
             read. --}}
        <x-alert variant="danger" :title="__('citizen.tracking.rejected_title')">
            <p class="u-flush">{{ $refus }}</p>
        </x-alert>
    @endif

    <x-card :title="__('citizen.tracking.timeline_title')">
        <ol class="timeline">
            @foreach ($etapes as $etape)
                <li class="timeline__item timeline__item--{{ $etape['etat'] }}">
                    <span class="timeline__marker" aria-hidden="true">
                        @if ($etape['etat'] === 'fait') &check;
                        @elseif ($etape['etat'] === 'arrete') &times;
                        @else {{ $loop->iteration }}
                        @endif
                    </span>
                    <div>
                        <strong>{{ $etape['titre'] }}</strong>
                        {{-- State is never carried by colour alone (WCAG 1.4.1). --}}
                        <span class="timeline__state">
                            @switch ($etape['etat'])
                                @case('fait') {{ __('citizen.tracking.state_done') }} @break
                                @case('en_cours') {{ __('citizen.tracking.state_current') }} @break
                                @case('arrete') {{ __('citizen.tracking.state_stopped') }} @break
                                @default {{ __('citizen.tracking.state_upcoming') }}
                            @endswitch
                        </span>
                        @if ($etape['detail'] !== '')
                            <br><span class="field__hint">{{ $etape['detail'] }}</span>
                        @endif
                        @if ($etape['date'])
                            <br><span class="field__hint">{{ $etape['date'] }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- No figure for how long: question D8 of COMPLIANCE_OPEN_QUESTIONS.md
             is open, and inventing a delay would be worse than announcing none.

             And on a rejected or cancelled request, no promise of news about a
             change that will not happen (D-075). --}}
        @unless ($demande->status->isStopped() || $demande->signature)
            <p class="field__hint">{{ __('citizen.tracking.will_be_told') }}</p>
        @endunless
    </x-card>

    @if ($demande->signature)
        <x-card :title="__('citizen.tracking.my_certificate')">
            <p>{{ __('citizen.tracking.signed_on', [
                'date' => $demande->signature->signed_at?->translatedFormat('d F Y'),
                'mayor' => $demande->signature->mayor?->name,
            ]) }}</p>

            @unless ($demande->signature->legally_binding)
                <x-alert variant="attention" :title="__('citizen.tracking.demo_title')">
                    {{ __('citizen.tracking.demo_body') }}
                </x-alert>
            @endunless

            <div class="row-actions">
                <x-button href="{{ route('acts.document', $demande->signature) }}" variant="primary">{{ __('citizen.tracking.download') }}</x-button>
                <x-button href="{{ route('acts.proof', $demande->signature) }}" variant="secondary">{{ __('citizen.tracking.signature_proof') }}</x-button>
            </div>
        </x-card>
    @endif

    <x-card :title="__('citizen.tracking.my_documents')">
        <div class="grid grid--2">
            @forelse ($demande->attachments as $piece)
                @php $estSelfie = $piece->kind === 'selfie'; @endphp
                <div>
                    <p><strong>{{ $estSelfie ? __('citizen.tracking.your_photo') : __('citizen.tracking.your_id') }}</strong></p>
                    <img class="capture__preview" src="{{ route('citizen.attachments.show', $piece) }}"
                         alt="{{ $estSelfie ? __('citizen.tracking.your_photo') : __('citizen.tracking.your_id') }}">
                    <p class="field__hint">{{ __('citizen.tracking.size_kb', ['size' => round($piece->size_bytes / 1024)]) }}</p>
                </div>
            @empty
                <p class="field__hint">{{ __('citizen.tracking.no_documents') }}</p>
            @endforelse
        </div>
    </x-card>

    @if ($demande->status !== \App\Enums\RequestStatus::Draft)
        <x-message-thread :demande="$demande" :messages="$messages" />
    @endif

    @can('cancel', $demande)
        <x-card :title="__('citizen.tracking.cancel_title')">
            <p>{!! __('citizen.tracking.cancel_body', ['strong' => '<strong>'.e(__('citizen.tracking.cancel_strong')).'</strong>']) !!}</p>

            <form method="POST" action="{{ route('citizen.requests.cancel', $demande) }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="cancel-reason">
                        {{ __('citizen.tracking.cancel_reason') }}
                        <span class="field__hint">{{ __('citizen.tracking.cancel_reason_hint') }}</span>
                    </label>
                    <textarea class="field__control" id="cancel-reason" name="reason" rows="2">{{ old('reason') }}</textarea>
                    @if ($errors->has('reason'))
                        <p class="field__error">{{ $errors->first('reason') }}</p>
                    @endif
                </div>

                <x-button type="submit" variant="danger">{{ __('citizen.tracking.cancel_action') }}</x-button>
            </form>
        </x-card>
    @endcan

    <p class="u-return"><a href="{{ route('citizen.requests.index') }}">{{ __('citizen.tracking.back_to_requests') }}</a></p>
@endsection
