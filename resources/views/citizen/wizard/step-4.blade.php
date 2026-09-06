@extends('citizen.wizard._layout')

@section('wizard')
    @php
        $selfie = $draft->attachments->firstWhere('kind', 'selfie');
        $piece = $draft->attachments->firstWhere('kind', 'id_document');
        $complet = $selfie && $piece;
    @endphp

    <x-card title="Vérifiez votre demande">
        <p>Relisez ces informations. Une fois envoyée, votre demande ne pourra
        plus être modifiée : il faudrait en déposer une nouvelle.</p>

        <h3>Votre demande</h3>
        <dl class="review">
            <div class="review__row"><dt>Motif</dt><dd>{{ $draft->reason === 'lost' ? 'Acte perdu' : 'Acte détérioré' }}</dd></div>
            <div class="review__row"><dt>Exemplaires</dt><dd>{{ $draft->copies_requested }}</dd></div>
            <div class="review__row"><dt>Centre</dt><dd>{{ $draft->center?->name ?? '—' }}</dd></div>
        </dl>

        <h3>L'acte recherché</h3>
        <dl class="review">
            <div class="review__row"><dt>Nom à la naissance</dt><dd>{{ $draft->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>Date de naissance</dt><dd>{{ $draft->date_of_birth?->translatedFormat('d F Y') }}</dd></div>
            <div class="review__row"><dt>Lieu</dt><dd>{{ $draft->place_of_birth }}</dd></div>
            <div class="review__row"><dt>Année d'enregistrement</dt><dd>{{ $draft->registration_year }}</dd></div>
            <div class="review__row"><dt>Numéro d'origine</dt><dd>{{ $draft->original_certificate_number ?: 'Non renseigné' }}</dd></div>
            <div class="review__row"><dt>Père</dt><dd>{{ $draft->father_name }} ({{ $draft->father_nationality }})</dd></div>
            <div class="review__row"><dt>Mère</dt><dd>{{ $draft->mother_name }} ({{ $draft->mother_nationality }})</dd></div>
            <div class="review__row"><dt>Adresse des parents</dt><dd>{{ $draft->parents_address }}</dd></div>
        </dl>

        <h3>Vos pièces</h3>
        <dl class="review">
            <div class="review__row">
                <dt>Votre photo</dt>
                <dd>@if ($selfie)<span class="badge badge--success">Enregistrée</span>@else<span class="badge badge--danger">Manquante</span>@endif</dd>
            </div>
            <div class="review__row">
                <dt>Pièce d'identité</dt>
                <dd>@if ($piece)<span class="badge badge--success">Enregistrée</span>@else<span class="badge badge--danger">Manquante</span>@endif</dd>
            </div>
        </dl>

        <p><a href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3]) }}">Corriger le centre ou les photos</a></p>
    </x-card>

    @unless ($complet)
        <x-alert variant="danger" title="Il manque une pièce"
                 :action="route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3])"
                 action-label="Ajouter la photo manquante">
            Votre demande ne peut pas être envoyée sans votre photo et celle de
            votre pièce d'identité.
        </x-alert>
    @endunless

    <x-card title="Envoi">
        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 4]) }}">
            @csrf
            <p>En envoyant cette demande, vous confirmez que les informations
            fournies sont exactes et vous acceptez que vos pièces soient
            consultées par l'officier d'état civil chargé de l'instruction.</p>

            <div class="actions">
                <x-button href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 3]) }}" variant="secondary">Retour</x-button>
                <x-button type="submit" variant="primary" @disabled(! $complet)>Envoyer ma demande</x-button>
            </div>
        </form>
    </x-card>
@endsection
