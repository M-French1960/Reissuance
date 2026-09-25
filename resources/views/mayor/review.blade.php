@extends('layouts.app')
@section('title', __('mayor.review.title', ['reference' => $demande->reference]))

@section('content')
    <h1>{{ __('mayor.review.title', ['reference' => $demande->reference]) }}</h1>
    <p class="u-note">
        <x-status-badge :status="$demande->status" />
        {{ __('mayor.review.meta', [
            'centre' => $demande->center?->name,
            'date' => $demande->submitted_at?->translatedFormat('d/m/Y'),
        ]) }}
        @if ($demande->verification_cycle > 1)
            {{ __('mayor.review.pass_number', ['number' => $demande->verification_cycle]) }}
        @endif
    </p>

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">{{ __('officer.action_impossible') }}</p>
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- What decides, first and without scrolling (§8.2). --}}
    <x-card :title="__('mayor.review.verification_title')">
        <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('mayor.review.each_step_aria') }}">
            <table>
                <caption class="visually-hidden">{{ __('mayor.review.each_step_aria') }}</caption>
                <thead><tr>
                    <th scope="col">{{ __('verification.step_column') }}</th>
                    <th scope="col">{{ __('common.result') }}</th>
                    <th scope="col">{{ __('common.by') }}</th>
                </tr></thead>
                <tbody>
                    {{-- The four checks. Step five is the officer's decision,
                         shown lower down in the decision history (D-027). --}}
                    @foreach (\App\Services\VerificationWorkflow::VERIFICATION_STEPS as $numero)
                        @php $e = $etapes->get($numero); @endphp
                        <tr>
                            <td data-label="{{ __('verification.step_column') }}">{{ $numero }}. {{ $steps[$numero] }}</td>
                            <td data-label="{{ __('common.result') }}">
                                @if ($e?->result)
                                    <span class="badge badge--{{ $e->result->tone() }}">{{ $e->result->label() }}</span>
                                @else
                                    <span class="badge badge--danger">{{ __('verification.not_recorded') }}</span>
                                @endif
                            </td>
                            <td data-label="{{ __('common.by') }}">{{ $e?->officer?->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($reservations !== [])
            <x-alert variant="attention" :title="__('mayor.review.reservation_title')">
                <ul class="alert__list">
                    @foreach ($reservations as $numero => $resultat)
                        <li><strong>{{ $numero }}. {{ $steps[$numero] }}</strong> {{ $resultat->label() }}</li>
                    @endforeach
                </ul>
                {{ __('mayor.review.reservation_body') }}
            </x-alert>
        @endif

        @unless ($complet)
            <x-alert variant="danger" :title="__('mayor.review.incomplete_title')">
                {{ __('mayor.review.incomplete_body', [
                    'steps' => collect($manquantes)->map(fn ($n) => "{$n}. {$steps[$n]}")->implode(', '),
                ]) }}
            </x-alert>
        @endunless
    </x-card>

    @if ($demande->decisions->isNotEmpty())
        <x-card :title="__('mayor.review.history_title')">
            <ol class="timeline">
                @foreach ($demande->decisions->sortBy('created_at') as $d)
                    <li class="timeline__item timeline__item--fait">
                        <span class="timeline__marker" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $d->decision->label() }}</strong>
                            <span class="u-note">{{ __('mayor.review.by_on', [
                                'name' => $d->actor?->name,
                                'role' => $d->actor_role->label(),
                                'date' => $d->created_at?->translatedFormat('d/m/Y H:i'),
                            ]) }}</span>
                            @if ($d->reason)<br><span class="u-note">{{ $d->reason }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card>
    @endif

    <x-card :title="__('mayor.review.documents_title')">
        <div class="compare">
            @foreach ([[__('mayor.review.selfie'), 'selfie'], [__('mayor.review.id_document'), 'id_document']] as [$titre, $kind])
                @php $piece = $demande->attachments->firstWhere('kind', $kind); @endphp
                <figure class="compare__pane">
                    <figcaption>{{ $titre }}</figcaption>
                    @if ($piece)
                        <a href="{{ route('citizen.attachments.show', $piece) }}" target="_blank" rel="noopener">
                            <img class="compare__image" src="{{ route('citizen.attachments.show', $piece) }}"
                                 alt="{{ __('mayor.review.open_larger', ['title' => $titre]) }}">
                        </a>
                    @else
                        <p class="badge badge--danger">{{ __('mayor.review.missing_document') }}</p>
                    @endif
                </figure>
            @endforeach
        </div>
    </x-card>

    {{--
        THE DRAFT THE MAYOR IS ABOUT TO SIGN (D-068).

        Placed BEFORE the file details, not in an appendix: the mayor signs the
        DRAFT written by the officer, not the fields shown below. While no
        screen offered it to open, they signed a document they had never seen.
        The content fingerprint proved the text had not moved; it did not prove
        it had been read.
    --}}
    @if ($projet)
        <x-card :title="__('mayor.review.draft_title')">
            <p>
                {{ __('mayor.review.draft_meta', [
                    'officer' => $projet->officer?->name ?? __('mayor.review.written_by_unknown'),
                    {{-- Le mot qui joint la date a l'heure vit dans les
                         fichiers de langue : un format Carbon ne traduit ni
                         « à » ni « at » (D-077). --}}
                    'date' => $projet->created_at === null ? '' : __('common.date_and_time', [
                        'date' => $projet->created_at->translatedFormat('d F Y'),
                        'time' => $projet->created_at->format('H:i'),
                    ]),
                ]) }}
                <strong>{{ __('mayor.review.draft_strong') }}</strong>
            </p>
            <div class="row-actions">
                <x-button href="{{ route('acts.draft', $projet) }}" variant="primary">
                    {{ __('mayor.review.read_draft') }}
                </x-button>
            </div>
        </x-card>
    @else
        <x-alert variant="attention" :title="__('mayor.review.no_draft_title')">
            {{ __('mayor.review.no_draft_body') }}
        </x-alert>
    @endif

    <x-card :title="__('mayor.review.certificate_title')">
        <dl class="review">
            <div class="review__row"><dt>{{ __('mayor.review.name_at_birth') }}</dt><dd>{{ $demande->full_name_at_birth }}</dd></div>
            {{-- Le libelle ne se repete pas dans la valeur : la ligne lisait
                 « Born on | Born on 15 January 1990 in Yaoundé » (D-077). --}}
            <div class="review__row"><dt>{{ __('wizard.step4.born_on') }}</dt><dd>{{ __('mayor.review.born_on_value', [
                'date' => $demande->date_of_birth?->translatedFormat('d F Y'),
                'place' => $demande->place_of_birth,
            ]) }}</dd></div>
            <div class="review__row"><dt>{{ __('mayor.review.registration_year') }}</dt><dd>{{ $demande->registration_year }}</dd></div>
            <div class="review__row"><dt>{{ __('mayor.review.father') }}</dt><dd>{{ $demande->father_name }}</dd></div>
            <div class="review__row"><dt>{{ __('mayor.review.mother') }}</dt><dd>{{ $demande->mother_name }}</dd></div>
            <div class="review__row"><dt>{{ __('mayor.review.copies') }}</dt><dd>{{ $demande->copies_requested }}</dd></div>
        </dl>
    </x-card>

    @if ($demande->signature)
        <x-card :title="__('mayor.review.issued_title')">
            <p>{{ __('mayor.review.issued_body', [
                'date' => $demande->signature->signed_at === null ? '' : __('common.date_and_time', [
                    'date' => $demande->signature->signed_at->translatedFormat('d F Y'),
                    'time' => $demande->signature->signed_at->format('H:i'),
                ]),
                'mayor' => $demande->signature->mayor?->name,
            ]) }}</p>
            @unless ($demande->signature->legally_binding)
                <x-alert variant="attention" :title="__('mayor.review.no_legal_value_title')">
                    {{ __('mayor.review.no_legal_value_body') }}
                </x-alert>
            @endunless
            <div class="row-actions">
                <x-button href="{{ route('acts.document', $demande->signature) }}" variant="secondary">{{ __('mayor.review.download') }}</x-button>
                <x-button href="{{ route('acts.proof', $demande->signature) }}" variant="secondary">{{ __('mayor.review.signature_proof') }}</x-button>
            </div>
        </x-card>
    @else
        <x-message-thread :demande="$demande" :messages="$messages" />

        <x-card :title="__('mayor.review.decision_title')">
            <p>{{ $estEscaladee ? __('mayor.review.escalated_intro') : __('mayor.review.ready_for_signature') }}</p>

            {{--
                ONE form, three submit buttons told apart by formaction.
                Without that, the reason typed would not follow the button
                chosen: the mayor would type an instruction, click "Return",
                and the officer would receive an empty reason. Plain HTML, no
                JavaScript needed.
            --}}
            <form method="POST" action="{{ route('mayor.sign', $demande) }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="reason">
                        {{ __('mayor.review.reason') }}
                        <span class="field__hint">
                            {{ $estEscaladee ? __('mayor.review.reason_required_escalated') : __('mayor.review.reason_required_return') }}
                        </span>
                    </label>
                    <textarea class="field__control" id="reason" name="reason" rows="4"
                              @if ($errors->has('reason')) aria-invalid="true" @endif>{{ old('reason') }}</textarea>
                    @if ($errors->has('reason'))<p class="field__error">{{ $errors->first('reason') }}</p>@endif
                </div>

                {{--
                    IDENTITY CONFIRMATION, TIED TO THE BUTTON THAT SIGNS.

                    No `required` attribute: the form carries three buttons, and
                    making this field required in HTML would also block "Return"
                    and "Reject", which do not need it. The server demands it,
                    for the signature alone. See DecisionController::sign().
                --}}
                <div class="field field--framed">
                    <label class="field__label" for="confirmation_code">
                        {{ __('mayor.review.confirmation_code') }} <span aria-hidden="true">*</span>
                        <span class="field__hint">{{ __('mayor.review.confirmation_hint') }}</span>
                    </label>
                    <input class="field__control" id="confirmation_code" name="confirmation_code"
                           type="text" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="64" spellcheck="false"
                           @if ($errors->has('confirmation_code')) aria-invalid="true" @endif>
                    @if ($errors->has('confirmation_code'))
                        <p class="field__error">{{ $errors->first('confirmation_code') }}</p>
                    @endif
                </div>

                {{--
                    SIGNING WITH THE DEVICE (D-070).

                    `hidden` by default: the script only reveals this block if
                    the browser knows WebAuthn and the page is served in a
                    secure context. Otherwise the code above stays the way to
                    sign. A town hall does not stop issuing certificates
                    because a browser is old or a phone was lost.
                --}}
                <div data-webauthn hidden class="u-stack-top">
                    <input type="hidden" name="device_assertion" id="device_assertion">
                    <x-button type="button" variant="primary" id="bouton-signer-appareil"
                              data-challenge-url="{{ route('mayor.device-challenge', $demande) }}"
                              data-sign-url="{{ route('mayor.sign', $demande) }}"
                              :disabled="! $complet">
                        {{ __('mayor.review.sign_with_device') }}
                    </x-button>
                    <p id="message-signature" class="u-note">{{ __('mayor.review.device_note') }}</p>
                </div>

                <div class="row-actions">
                    <x-button type="submit" variant="primary"
                              formaction="{{ route('mayor.sign', $demande) }}"
                              :disabled="! $complet">
                        {{ $estEscaladee ? __('mayor.review.sign_by_exception') : __('mayor.review.sign') }}
                    </x-button>

                    <x-button type="submit" variant="secondary"
                              formaction="{{ route('mayor.return', $demande) }}">
                        {{ __('mayor.review.return_to_officer') }}
                    </x-button>

                    @if ($estEscaladee)
                        <x-button type="submit" variant="danger"
                                  formaction="{{ route('mayor.reject', $demande) }}">
                            {{ __('mayor.review.reject') }}
                        </x-button>
                    @endif
                </div>
            </form>

            @unless ($complet)
                <p class="u-note">{{ __('mayor.review.signature_unavailable') }}</p>
            @endunless
        </x-card>
    @endif

    <p class="u-return"><a href="{{ route('mayor.dashboard') }}">{{ __('mayor.review.back_to_dashboard') }}</a></p>
@endsection
