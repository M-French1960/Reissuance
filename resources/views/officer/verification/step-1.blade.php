@extends('officer.verification._layout')
@section('etape')
    <x-card title="Informations de la demande">
        @include('officer.verification.result', ['etape' => $etapes->get(1)])

        <h3>Le demandeur</h3>
        <dl class="review">
            <div class="review__row"><dt>Nom du compte</dt><dd>{{ $demande->citizen->name }}</dd></div>
            <div class="review__row"><dt>Nom au profil</dt><dd>{{ $demande->citizen->profile?->fullName() ?? '—' }}</dd></div>
            <div class="review__row"><dt>Date de naissance</dt><dd>{{ $demande->citizen->profile?->birth_date?->translatedFormat('d F Y') ?? '—' }}</dd></div>
            <div class="review__row"><dt>Téléphone</dt><dd>{{ $demande->citizen->profile?->phone ?? '—' }}</dd></div>
        </dl>

        <h3>L'acte demandé</h3>
        <dl class="review">
            <div class="review__row"><dt>Motif</dt><dd>{{ $demande->reason === 'lost' ? 'Acte perdu' : 'Acte détérioré' }}</dd></div>
            <div class="review__row"><dt>Nom à la naissance</dt><dd>{{ $demande->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>Date de naissance</dt><dd>{{ $demande->date_of_birth?->translatedFormat('d F Y') }}</dd></div>
            <div class="review__row"><dt>Lieu</dt><dd>{{ $demande->place_of_birth }}</dd></div>
            <div class="review__row"><dt>Année d'enregistrement</dt><dd>{{ $demande->registration_year }}</dd></div>
            <div class="review__row"><dt>Numéro d'origine</dt><dd>{{ $demande->original_certificate_number ?: 'Non renseigné' }}</dd></div>
            <div class="review__row"><dt>Père</dt><dd>{{ $demande->father_name }} ({{ $demande->father_nationality }})</dd></div>
            <div class="review__row"><dt>Mère</dt><dd>{{ $demande->mother_name }} ({{ $demande->mother_nationality }})</dd></div>
        </dl>

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.acknowledge', ['reissuanceRequest' => $demande, 'step' => 1]) }}">
                @csrf
                <fieldset class="fieldset">
                    <legend class="field__label">Les informations sont-elles cohérentes ? <span aria-hidden="true">*</span></legend>
                    @foreach ([
                        'match' => 'Oui, les informations sont cohérentes',
                        'inconclusive' => "Des points restent à éclaircir",
                        'no_match' => 'Non, des incohérences manifestes',
                    ] as $valeur => $libelle)
                        <div class="field--inline">
                            <input type="radio" id="r1-{{ $valeur }}" name="result" value="{{ $valeur }}" class="field__checkbox" required>
                            <label for="r1-{{ $valeur }}">{{ $libelle }}</label>
                        </div>
                    @endforeach
                </fieldset>
                <x-field name="note" label="Observation" hint="Facultative. Visible dans le dossier." />
                <x-button type="submit" variant="primary">Enregistrer et continuer</x-button>
            </form>
        @endif
    </x-card>
@endsection
