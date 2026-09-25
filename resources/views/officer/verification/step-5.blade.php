@extends('officer.verification._layout')
@section('etape')
    <x-card :title="__('verification.summary_title')">
        <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('verification.summary_title') }}">
            <table>
                <caption class="visually-hidden">{{ __('verification.summary_title') }}</caption>
                <thead><tr>
                    <th scope="col">{{ __('verification.step_column') }}</th>
                    <th scope="col">{{ __('common.result') }}</th>
                    <th scope="col">{{ __('common.recorded') }}</th>
                </tr></thead>
                <tbody>
                    {{-- The four checks. Step five is the decision below: it has
                         no result to record here, and requiring one made
                         acceptance unreachable (D-027). --}}
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
                            <td data-label="{{ __('common.recorded') }}">{{ $e?->completed_at?->translatedFormat('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td data-label="{{ __('verification.step_column') }}">5. {{ $steps[5] }}</td>
                        <td data-label="{{ __('common.result') }}"><span class="badge badge--neutral">{{ __('verification.in_progress') }}</span></td>
                        <td data-label="{{ __('common.recorded') }}"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($reservations !== [])
            <x-alert variant="attention" :title="__('officer.step5.reservations_title')">
                <ul class="reasons__list">
                    @foreach ($reservations as $numero => $resultat)
                        <li><strong>{{ $numero }}. {{ $steps[$numero] }}</strong> {{ $resultat->label() }}</li>
                    @endforeach
                </ul>
                {{ __('officer.step5.reservations_body') }}
            </x-alert>
        @endif

        @unless ($complet)
            <x-alert variant="danger" :title="__('verification.incomplete_title')">
                {{ __('verification.incomplete_body', [
                    'steps' => collect($manquantes)->map(fn ($n) => "{$n}. {$steps[$n]}")->implode(', '),
                ]) }}
                <br>{{ __('verification.incomplete_note') }}
            </x-alert>
        @endunless
    </x-card>

    <x-message-thread :demande="$demande" :messages="$messages" />

    @if ($peutDecider)
        <x-card :title="__('officer.step5.decision_title')">
            <form method="POST" action="{{ route('officer.decision.store', $demande) }}" data-decision-form>
                @csrf

                <fieldset class="fieldset">
                    {{-- Nothing preselected: in the prototype "Accept" was the
                         menu default and a stray click meant acceptance. --}}
                    <legend class="field__label">{{ __('officer.step5.decision_legend') }} <span aria-hidden="true">*</span></legend>
                    @foreach ([
                        'accepted' => [__('officer.step5.accept'), __('officer.step5.accept_help')],
                        'rejected' => [__('officer.step5.reject'), __('officer.step5.reject_help')],
                        'escalated' => [__('officer.step5.escalate'), __('officer.step5.escalate_help')],
                    ] as $valeur => [$libelle, $aide])
                        <div class="field--inline">
                            <input type="radio" id="d-{{ $valeur }}" name="decision" value="{{ $valeur }}"
                                   class="field__checkbox" required
                                   @checked(old('decision') === $valeur)
                                   @if ($valeur === 'accepted' && ! $complet) disabled @endif>
                            <label for="d-{{ $valeur }}">
                                {{ $libelle }}
                                <span class="u-note">{{ $aide }}</span>
                                @if ($valeur === 'accepted' && ! $complet)
                                    <span class="u-note">({{ __('officer.step5.accept_unavailable') }})</span>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </fieldset>

                <div class="field">
                    <label class="field__label" for="reason">
                        {{ __('officer.step5.reason') }} <span aria-hidden="true">*</span>
                        <span class="field__hint">
                            {{ $reservations !== [] ? __('officer.step5.reason_required_reservation') : __('officer.step5.reason_required_normal') }}
                            {{ __('officer.step5.reason_note') }}
                        </span>
                    </label>
                    <textarea class="field__control" id="reason" name="reason" rows="4"
                              @if ($errors->has('reason')) aria-invalid="true" @endif>{{ old('reason') }}</textarea>
                    @if ($errors->has('reason'))
                        <p class="field__error">{{ $errors->first('reason') }}</p>
                    @endif
                </div>

                {{-- Prefilled reasons: optimising for repetition (§8.2). --}}
                <details class="reasons">
                    <summary>{{ __('officer.step5.common_reasons') }}</summary>
                    <ul class="reasons__list">
                        @foreach (\App\Http\Controllers\Officer\DecisionController::rejectionReasons() as $motif)
                            <li><button type="button" class="btn btn--secondary reasons__pick" data-reason="{{ $motif }}">{{ $motif }}</button></li>
                        @endforeach
                    </ul>
                    <p class="u-note">{{ __('officer.step5.no_js_note') }}</p>
                </details>

                <div class="field">
                    <label class="field__label" for="internal_notes">{{ __('officer.step5.internal_notes') }}</label>
                    <span class="field__hint">{{ __('officer.step5.internal_notes_hint') }}</span>
                    <textarea class="field__control" id="internal_notes" name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
                </div>

                <x-button type="submit" variant="primary">{{ __('officer.step5.save_decision') }}</x-button>
            </form>

            {{--
                CONFIRMATION D'UNE DECISION IRREVERSIBLE.

                Elle transmet l'acte au maire, ou elle refuse la demande d'etat
                civil de quelqu'un et lui en notifie le motif. Le dialogue
                reprend la decision choisie EN TOUTES LETTRES : un « Etes-vous
                sûr ? » qui ne dit pas de quoi ne fait qu'ajouter un clic.

                C'est un filet, pas une barriere. Sans JavaScript, ou sans
                <dialog>, le formulaire part directement et les controles du
                serveur sont identiques.
            --}}
            <dialog class="decision-confirm" data-decision-dialog aria-labelledby="confirmer-titre">
                <h2 id="confirmer-titre">{{ __('officer.step5.confirm_title') }}</h2>
                <p>
                    {{ __('officer.step5.confirm_body') }}
                    <span class="decision-confirm__choice" data-decision-summary></span>
                </p>
                <div class="decision-confirm__actions">
                    <button type="button" class="btn btn--primary" data-decision-confirm>{{ __('officer.step5.confirm_yes') }}</button>
                    <button type="button" class="btn btn--secondary" data-decision-cancel>{{ __('common.cancel') }}</button>
                </div>
            </dialog>
        </x-card>
    @endif
@endsection
