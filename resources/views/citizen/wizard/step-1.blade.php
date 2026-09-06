@extends('citizen.wizard._layout')

@section('wizard')
    <x-card title="Vos informations">
        <p>Ces informations viennent de votre profil. Vérifiez-les avant de continuer.</p>

        <dl class="review">
            <div class="review__row"><dt>Nom</dt><dd>{{ $profile->fullName() }}</dd></div>
            <div class="review__row"><dt>Date de naissance</dt><dd>{{ $profile->birth_date?->translatedFormat('d F Y') ?? '—' }}</dd></div>
            <div class="review__row"><dt>Lieu de naissance</dt><dd>{{ $profile->birth_place ?? '—' }}</dd></div>
            <div class="review__row"><dt>Téléphone</dt><dd>{{ $profile->phone ?? '—' }}</dd></div>
            <div class="review__row"><dt>Adresse</dt><dd>{{ $profile->address ?? '—' }}</dd></div>
        </dl>

        <p><a href="{{ route('citizen.profile.edit') }}">Corriger mon profil</a></p>
    </x-card>

    <x-card title="Motif de la demande">
        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 1]) }}">
            @csrf

            <fieldset class="fieldset">
                <legend class="field__label">Pourquoi demandez-vous une réédition ? <span aria-hidden="true">*</span></legend>
                @foreach (['lost' => 'J\'ai perdu mon acte de naissance', 'damaged' => 'Mon acte est détérioré ou illisible'] as $value => $label)
                    <div class="field--inline">
                        <input type="radio" id="reason-{{ $value }}" name="reason" value="{{ $value }}"
                               class="field__checkbox" required
                               @checked(old('reason', $draft->reason) === $value)>
                        <label for="reason-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </fieldset>

            <x-field name="copies_requested" label="Nombre d'exemplaires souhaités" type="number"
                     :value="$draft->copies_requested ?? 1" required min="1" max="10"
                     hint="Entre 1 et 10." :error="$errors->first('copies_requested')" />

            <x-button type="submit" variant="primary">Continuer</x-button>
        </form>
    </x-card>
@endsection
