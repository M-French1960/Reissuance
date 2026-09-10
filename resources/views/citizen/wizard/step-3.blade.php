@extends('citizen.wizard._layout')

@section('wizard')
    @php
        $selfie = $draft->attachments->firstWhere('kind', 'selfie');
        $piece = $draft->attachments->firstWhere('kind', 'id_document');
    @endphp

    <div class="alert alert--danger" role="alert" data-offline-banner hidden>
        <p class="alert__title">Connexion perdue</p>
        Vos étapes déjà validées sont enregistrées. Attendez le retour de la
        connexion avant d'envoyer une photo.
    </div>

    <x-card title="Centre d'état civil">
        <p>Choisissez le centre où votre naissance a été enregistrée. C'est lui
        qui instruira votre demande.</p>

        <form method="POST" action="{{ route('citizen.requests.save', ['reissuanceRequest' => $draft, 'step' => 3]) }}" id="centre-form">
            @csrf
            <div class="field">
                <label class="field__label" for="civil_status_center_id">
                    Centre d'état civil <span aria-hidden="true">*</span>
                </label>
                <select class="field__control" id="civil_status_center_id" name="civil_status_center_id" required
                        @if ($errors->has('civil_status_center_id')) aria-invalid="true" @endif>
                    <option value="">Choisissez un centre</option>
                    @foreach ($centers as $center)
                        <option value="{{ $center->id }}" @selected(old('civil_status_center_id', $draft->civil_status_center_id) == $center->id)>
                            {{ $center->name }} — {{ $center->commune?->name }}
                        </option>
                    @endforeach
                </select>
                @if ($errors->has('civil_status_center_id'))
                    <p class="field__error">{{ $errors->first('civil_status_center_id') }}</p>
                @endif
            </div>
        </form>
    </x-card>

    <x-card title="Vos pièces justificatives">
        <p>Deux photos sont nécessaires. Elles servent à l'officier d'état civil
        pour vérifier que vous êtes bien la personne concernée par l'acte.</p>

        @if ($errors->has('file') || $errors->has('attachments'))
            <x-alert variant="danger" title="La photo n'a pas été enregistrée">
                {{ $errors->first('file') ?: $errors->first('attachments') }}
            </x-alert>
        @endif

        {{-- Information sur le traitement biometrique. Le rapprochement est
             obligatoire (D-045) : il n'y a donc pas de case a cocher, mais la
             personne doit savoir ce qui sera fait de sa photographie, et que
             la decision reste humaine. --}}
        <x-alert variant="attention" title="Vos deux photographies seront comparées">
            <p>
                Un rapprochement automatique sera fait entre votre photo et celle
                de votre pièce d'identité, afin de vérifier que la pièce est bien
                la vôtre. <strong>Cette comparaison ne décide pas :</strong> elle
                donne un avis à l'officier d'état civil, qui examine lui-même les
                deux photographies et reste seul à décider.
            </p>
            <p class="u-flush">
                Si la comparaison échoue ou n'aboutit pas, votre demande
                <strong>n'est pas refusée pour autant</strong> : l'officier
                poursuit l'examen et doit motiver sa décision.
            </p>
        </x-alert>

        <div class="grid grid--2">
            @foreach ([
                ['selfie', 'Votre photo', $selfie, "Prenez-vous en photo, visage bien visible et de face. C'est ce qui permet de vérifier que la pièce est bien la vôtre.", 'user'],
                ['id_document', "Votre pièce d'identité", $piece, "Photographiez votre carte nationale d'identité ou votre passeport, en entier et bien lisible.", 'environment'],
            ] as [$kind, $titre, $existant, $aide, $facing])
                <div class="capture" data-capture data-capture-noscript>
                    <h3>{{ $titre }}</h3>
                    <p class="field__hint">{{ $aide }}</p>

                    <form method="POST"
                          action="{{ route('citizen.requests.attachments.store', $draft) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="kind" value="{{ $kind }}">

                        <img class="capture__preview" data-capture-preview alt="" hidden>

                        @if ($existant)
                            <p class="capture__state">
                                <span class="badge badge--success">Enregistrée</span>
                                <span class="field__hint">{{ round($existant->size_bytes / 1024) }} Ko — {{ $existant->captured_at?->translatedFormat('d/m/Y à H:i') }}</span>
                            </p>
                        @endif

                        <div class="field">
                            <label class="field__label" for="file-{{ $kind }}">
                                {{ $existant ? 'Reprendre la photo' : 'Prendre la photo' }}
                            </label>
                            <input class="field__control" type="file" id="file-{{ $kind }}" name="file"
                                   accept="image/jpeg,image/png,image/webp"
                                   capture="{{ $facing }}"
                                   data-capture-input required>
                            <span class="field__hint" data-capture-feedback>
                                La photo est réduite sur votre téléphone avant l'envoi, pour consommer moins de données.
                            </span>
                        </div>

                        <x-button type="submit" variant="secondary" data-capture-submit>
                            {{ $existant ? 'Remplacer' : 'Envoyer la photo' }}
                        </x-button>
                    </form>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="actions">
        <x-button href="{{ route('citizen.requests.step', ['reissuanceRequest' => $draft, 'step' => 2]) }}" variant="secondary">Retour</x-button>
        <x-button type="submit" variant="primary" form="centre-form">Continuer</x-button>
    </div>
@endsection
