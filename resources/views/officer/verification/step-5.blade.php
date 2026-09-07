@extends('officer.verification._layout')
@section('etape')
    <x-card title="Récapitulatif des vérifications">
        <div class="table-wrap">
            <table>
                <caption class="visually-hidden">Résultat de chaque étape</caption>
                <thead><tr><th scope="col">Étape</th><th scope="col">Résultat</th><th scope="col">Enregistré</th></tr></thead>
                <tbody>
                    {{-- Les quatre vérifications. La cinquième étape est la
                         décision ci-dessous : elle n'a pas de résultat à
                         renseigner ici, et l'exiger rendait l'acceptation
                         inatteignable (D-027). --}}
                    @foreach (\App\Services\VerificationWorkflow::VERIFICATION_STEPS as $numero)
                        @php $e = $etapes->get($numero); @endphp
                        <tr>
                            <td>{{ $numero }}. {{ $steps[$numero] }}</td>
                            <td>
                                @if ($e?->result)
                                    <span class="badge badge--{{ $e->result->tone() }}">{{ $e->result->label() }}</span>
                                @else
                                    <span class="badge badge--danger">Non renseignée</span>
                                @endif
                            </td>
                            <td>{{ $e?->completed_at?->translatedFormat('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td>5. {{ $steps[5] }}</td>
                        <td><span class="badge badge--neutral">En cours</span></td>
                        <td>—</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($reservations !== [])
            <x-alert variant="attention" title="Une vérification n'a pas abouti">
                <ul class="reasons__list">
                    @foreach ($reservations as $numero => $resultat)
                        <li><strong>{{ $numero }}. {{ $steps[$numero] }}</strong> — {{ $resultat->label() }}</li>
                    @endforeach
                </ul>
                Vous pouvez malgré tout accepter la demande : la décision vous
                appartient. Le motif devient alors <strong>obligatoire</strong>,
                il figurera au dossier et le maire le lira avant de signer.
            </x-alert>
        @endif

        @unless ($complet)
            <x-alert variant="danger" title="Vérification incomplète">
                Vous ne pourrez pas accepter cette demande tant que les quatre
                vérifications n'ont pas de résultat. Il manque :
                @foreach ($manquantes as $n)
                    <strong>{{ $n }}. {{ $steps[$n] }}</strong>@if (! $loop->last), @endif
                @endforeach.
                <br>Le rejet et l'escalade restent possibles.
            </x-alert>
        @endunless
    </x-card>

    @if ($peutDecider)
        <x-card title="Votre décision">
            <form method="POST" action="{{ route('officer.decision.store', $demande) }}">
                @csrf

                <fieldset class="fieldset">
                    {{-- Aucune valeur présélectionnée : dans le prototype,
                         « Accepter » était le choix par défaut du menu et un
                         clic accidentel valait acceptation. --}}
                    <legend class="field__label">Décision <span aria-hidden="true">*</span></legend>
                    @foreach ([
                        'accepted' => ["Accepter et transmettre au maire", "La demande passera en attente de signature."],
                        'rejected' => ['Rejeter la demande', 'Décision définitive. Le citoyen devra déposer une nouvelle demande.'],
                        'escalated' => ['Escalader au maire', "Pour un dossier douteux ou hors de votre compétence."],
                    ] as $valeur => [$libelle, $aide])
                        <div class="field--inline">
                            <input type="radio" id="d-{{ $valeur }}" name="decision" value="{{ $valeur }}"
                                   class="field__checkbox" required
                                   @checked(old('decision') === $valeur)
                                   @if ($valeur === 'accepted' && ! $complet) disabled @endif>
                            <label for="d-{{ $valeur }}">
                                {{ $libelle }}
                                <span class="u-note">— {{ $aide }}</span>
                                @if ($valeur === 'accepted' && ! $complet)
                                    <span class="u-note">(indisponible : vérification incomplète)</span>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </fieldset>

                <div class="field">
                    <label class="field__label" for="reason">
                        Motif <span aria-hidden="true">*</span>
                        <span class="field__hint">
                            @if ($reservations !== [])
                                Obligatoire ici : une vérification n'a pas abouti à une correspondance.
                            @else
                                Obligatoire pour un rejet ou une escalade.
                            @endif
                            Il figurera au dossier et au journal d'audit.
                        </span>
                    </label>
                    <textarea class="field__control" id="reason" name="reason" rows="4"
                              @if ($errors->has('reason')) aria-invalid="true" @endif>{{ old('reason') }}</textarea>
                    @if ($errors->has('reason'))
                        <p class="field__error">{{ $errors->first('reason') }}</p>
                    @endif
                </div>

                {{-- Motifs pré-remplis : on optimise pour la répétition (§8.2). --}}
                <details class="reasons">
                    <summary>Motifs fréquents</summary>
                    <ul class="reasons__list">
                        @foreach (\App\Http\Controllers\Officer\DecisionController::REJECTION_REASONS as $motif)
                            <li><button type="button" class="btn btn--secondary reasons__pick" data-reason="{{ $motif }}">{{ $motif }}</button></li>
                        @endforeach
                    </ul>
                    <p class="u-note">Sans JavaScript, recopiez le motif voulu dans le champ ci-dessus.</p>
                </details>

                <div class="field">
                    <label class="field__label" for="internal_notes">Notes internes</label>
                    <span class="field__hint">Facultatives. Non communiquées au citoyen.</span>
                    <textarea class="field__control" id="internal_notes" name="internal_notes" rows="3">{{ old('internal_notes') }}</textarea>
                </div>

                <x-button type="submit" variant="primary">Enregistrer ma décision</x-button>
            </form>
        </x-card>
    @endif
@endsection
