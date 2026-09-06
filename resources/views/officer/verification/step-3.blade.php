@extends('officer.verification._layout')
@section('etape')
    @php
        $selfie = $demande->attachments->firstWhere('kind', 'selfie');
        $piece = $demande->attachments->firstWhere('kind', 'id_document');
    @endphp

    <x-card title="Examen des photographies">
        <p class="u-note">Comparez le selfie et la pièce présentée. Cliquez sur une
        image pour l'agrandir. Chaque ouverture est journalisée.</p>

        @include('officer.verification.result', ['etape' => $etapes->get(3)])

        <div class="compare">
            @foreach ([['Selfie du demandeur', $selfie], ["Pièce d'identité", $piece]] as [$titre, $piece_])
                <figure class="compare__pane">
                    <figcaption>{{ $titre }}</figcaption>
                    @if ($piece_)
                        <a href="{{ route('citizen.attachments.show', $piece_) }}" target="_blank" rel="noopener">
                            <img class="compare__image" src="{{ route('citizen.attachments.show', $piece_) }}"
                                 alt="{{ $titre }} — ouvrir en grand">
                        </a>
                        <p class="u-note">{{ round($piece_->size_bytes / 1024) }} Ko ·
                        prise le {{ $piece_->captured_at?->translatedFormat('d/m/Y à H:i') }}</p>
                    @else
                        <p class="badge badge--danger">Pièce manquante</p>
                    @endif
                </figure>
            @endforeach
        </div>

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.acknowledge', ['reissuanceRequest' => $demande, 'step' => 3]) }}">
                @csrf
                <fieldset class="fieldset">
                    <legend class="field__label">Les photographies concordent-elles ? <span aria-hidden="true">*</span></legend>
                    @foreach ([
                        'match' => "Oui, la personne du selfie est bien celle de la pièce",
                        'inconclusive' => "Impossible de conclure (qualité insuffisante)",
                        'no_match' => 'Non, il ne semble pas s\'agir de la même personne',
                    ] as $valeur => $libelle)
                        <div class="field--inline">
                            <input type="radio" id="r3-{{ $valeur }}" name="result" value="{{ $valeur }}" class="field__checkbox" required>
                            <label for="r3-{{ $valeur }}">{{ $libelle }}</label>
                        </div>
                    @endforeach
                </fieldset>
                <x-field name="note" label="Observation" hint="Facultative." />
                <x-button type="submit" variant="primary">Enregistrer et continuer</x-button>
            </form>
        @endif
    </x-card>
@endsection
