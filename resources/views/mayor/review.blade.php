@extends('layouts.app')
@section('title', 'Dossier '.$demande->reference)

@section('content')
    <h1>Dossier {{ $demande->reference }}</h1>
    <p class="u-note">
        <x-status-badge :status="$demande->status" />
        · {{ $demande->center?->name }} · déposée le {{ $demande->submitted_at?->translatedFormat('d/m/Y') }}
        @if ($demande->verification_cycle > 1) · passe n°{{ $demande->verification_cycle }} @endif
    </p>

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">Action impossible</p>
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- L'essentiel d'abord : ce qui décide, sans défilement (§8.2). --}}
    <x-card title="Résultat de la vérification">
        <div class="table-wrap" tabindex="0" role="group" aria-label="Résultat de chaque étape">
            <table>
                <caption class="visually-hidden">Résultat de chaque étape</caption>
                <thead><tr><th scope="col">Étape</th><th scope="col">Résultat</th><th scope="col">Par</th></tr></thead>
                <tbody>
                    {{-- Les quatre vérifications. La cinquième étape est la
                         décision de l'officier, reprise plus bas dans le
                         récapitulatif des décisions (D-027). --}}
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
                            <td>{{ $e?->officer?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($reservations !== [])
            <x-alert variant="attention" title="L'officier a accepté malgré une réserve">
                <ul class="alert__list">
                    @foreach ($reservations as $numero => $resultat)
                        <li><strong>{{ $numero }}. {{ $steps[$numero] }}</strong> — {{ $resultat->label() }}</li>
                    @endforeach
                </ul>
                Lisez le motif que l'officier a dû fournir avant de signer.
            </x-alert>
        @endif

        @unless ($complet)
            <x-alert variant="danger" title="Vérification incomplète">
                Ce dossier ne peut pas être signé : il manque
                @foreach ($manquantes as $n)<strong>{{ $n }}. {{ $steps[$n] }}</strong>@if (! $loop->last), @endif @endforeach.
                Retournez-le à l'officier.
            </x-alert>
        @endunless
    </x-card>

    @if ($demande->decisions->isNotEmpty())
        <x-card title="Décisions prises sur ce dossier">
            <ol class="timeline">
                @foreach ($demande->decisions->sortBy('created_at') as $d)
                    <li class="timeline__item timeline__item--fait">
                        <span class="timeline__marker" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $d->decision->label() }}</strong>
                            <span class="u-note">par {{ $d->actor?->name }} ({{ $d->actor_role->label() }})
                            le {{ $d->created_at?->translatedFormat('d/m/Y à H:i') }}</span>
                            @if ($d->reason)<br><span class="u-note">{{ $d->reason }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card>
    @endif

    <x-card title="Pièces du demandeur">
        <div class="compare">
            @foreach ([['Selfie', 'selfie'], ["Pièce d'identité", 'id_document']] as [$titre, $kind])
                @php $piece = $demande->attachments->firstWhere('kind', $kind); @endphp
                <figure class="compare__pane">
                    <figcaption>{{ $titre }}</figcaption>
                    @if ($piece)
                        <a href="{{ route('citizen.attachments.show', $piece) }}" target="_blank" rel="noopener">
                            <img class="compare__image" src="{{ route('citizen.attachments.show', $piece) }}" alt="{{ $titre }} — ouvrir en grand">
                        </a>
                    @else
                        <p class="badge badge--danger">Pièce manquante</p>
                    @endif
                </figure>
            @endforeach
        </div>
    </x-card>

    {{--
        LE PROJET QUE LE MAIRE VA SIGNER (D-068).

        Place AVANT le detail du dossier, et non en annexe : le maire signe le
        PROJET redige par l'officier, pas les champs affiches ci-dessous. Tant
        qu'aucun ecran ne le lui donnait a ouvrir, il signait un document qu'il
        n'avait jamais vu — l'empreinte de contenu prouvait que le texte n'avait
        pas bouge, elle ne prouvait pas qu'il avait ete lu.
    --}}
    @if ($projet)
        <x-card title="Le projet d'acte à signer">
            <p>
                Rédigé par {{ $projet->officer?->name ?? 'un officier' }}
                le {{ $projet->created_at?->translatedFormat('d F Y à H:i') }}.
                <strong>C'est ce document que votre signature rendra définitif.</strong>
            </p>
            <div class="row-actions">
                <x-button href="{{ route('acts.draft', $projet) }}" variant="primary">
                    Lire le projet d'acte
                </x-button>
            </div>
        </x-card>
    @else
        <x-alert variant="attention" title="Aucun projet d'acte">
            L'officier n'a pas encore rédigé le projet. La signature sera refusée
            tant qu'il n'existe pas : le maire signe un projet établi par l'officier,
            il ne rédige pas l'acte.
        </x-alert>
    @endif

    <x-card title="L'acte demandé">
        <dl class="review">
            <div class="review__row"><dt>Nom à la naissance</dt><dd>{{ $demande->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>Né(e) le</dt><dd>{{ $demande->date_of_birth?->translatedFormat('d F Y') }} à {{ $demande->place_of_birth }}</dd></div>
            <div class="review__row"><dt>Année d'enregistrement</dt><dd>{{ $demande->registration_year }}</dd></div>
            <div class="review__row"><dt>Père</dt><dd>{{ $demande->father_name }}</dd></div>
            <div class="review__row"><dt>Mère</dt><dd>{{ $demande->mother_name }}</dd></div>
            <div class="review__row"><dt>Exemplaires</dt><dd>{{ $demande->copies_requested }}</dd></div>
        </dl>
    </x-card>

    @if ($demande->signature)
        <x-card title="Acte délivré">
            <p>Signé le {{ $demande->signature->signed_at?->translatedFormat('d F Y à H:i') }}
            par {{ $demande->signature->mayor?->name }}.</p>
            @unless ($demande->signature->legally_binding)
                <x-alert variant="attention" title="Sans valeur juridique">
                    Ce document a été produit par un adaptateur de démonstration.
                </x-alert>
            @endunless
            <div class="row-actions">
                <x-button href="{{ route('acts.document', $demande->signature) }}" variant="secondary">Télécharger l'acte</x-button>
                <x-button href="{{ route('acts.proof', $demande->signature) }}" variant="secondary">Preuve de signature</x-button>
            </div>
        </x-card>
    @else
    <x-message-thread :demande="$demande" :messages="$messages" />

        <x-card title="Votre décision">
            @if ($estEscaladee)
                <p>Ce dossier a été escaladé par l'officier. Trois issues vous sont ouvertes.</p>
            @else
                <p>Ce dossier a été validé par l'officier et attend votre signature.</p>
            @endif

            {{--
                Un SEUL formulaire, trois boutons d'envoi distingués par
                formaction. Sans cela, le motif saisi ne suivrait pas le
                bouton choisi : le maire taperait une consigne, cliquerait
                « Retourner », et l'officier recevrait un motif vide.
                HTML pur, aucun JavaScript nécessaire.
            --}}
            <form method="POST" action="{{ route('mayor.sign', $demande) }}">
                @csrf

                <div class="field">
                    <label class="field__label" for="reason">
                        Motif
                        <span class="field__hint">
                            @if ($estEscaladee)
                                Obligatoire quelle que soit votre décision : vous statuez sur un dossier signalé.
                            @else
                                Obligatoire pour retourner le dossier à l'officier.
                            @endif
                        </span>
                    </label>
                    <textarea class="field__control" id="reason" name="reason" rows="4"
                              @if ($errors->has('reason')) aria-invalid="true" @endif>{{ old('reason') }}</textarea>
                    @if ($errors->has('reason'))<p class="field__error">{{ $errors->first('reason') }}</p>@endif
                </div>

                <div class="row-actions">
                    <x-button type="submit" variant="primary"
                              formaction="{{ route('mayor.sign', $demande) }}"
                              :disabled="! $complet">
                        {{ $estEscaladee ? 'Approuver par exception et signer' : "Signer l'acte" }}
                    </x-button>

                    <x-button type="submit" variant="secondary"
                              formaction="{{ route('mayor.return', $demande) }}">
                        Retourner à l'officier
                    </x-button>

                    @if ($estEscaladee)
                        <x-button type="submit" variant="danger"
                                  formaction="{{ route('mayor.reject', $demande) }}">
                            Rejeter la demande
                        </x-button>
                    @endif
                </div>
            </form>

            @unless ($complet)
                <p class="u-note">La signature est indisponible tant que la vérification est incomplète.</p>
            @endunless
        </x-card>
    @endif

    <p class="u-return"><a href="{{ route('mayor.dashboard') }}">Retour au tableau de bord</a></p>
@endsection
