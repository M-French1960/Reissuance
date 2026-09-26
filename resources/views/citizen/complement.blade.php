@extends('layouts.app')
@section('title', __('citizen.complement.title'))

@section('content')
    {{--
        ENVOYER LA PIECE RECLAMEE (D-087).

        Cet ecran n'existe que si un officier a ouvert une demande encore en
        attente : la Policy `provideComplement` l'exige. Sans cela, n'importe
        qui pourrait remplacer apres coup une piece d'identite deja verifiee.

        LA MAQUETTE MONTRAIT L'ANCIENNE PHOTO A COTE DE LA NOUVELLE. C'est
        impossible, et c'est voulu : le magasin de pieces supprime la
        precedente. Une piece d'identite refusee n'est pas conservee pour etre
        montree. L'ecran le dit plutot que de laisser chercher.
    --}}
    <h1>{{ __('citizen.complement.title') }}</h1>

    <x-flash />

    <x-alert variant="attention" :title="__('citizen.complement.banner_title')">
        <p class="u-flush">
            {{ __('citizen.complement.what', [
                'piece' => __('citizen.complement.piece_'.$complement->kind),
            ]) }}
        </p>
        <p class="u-flush">{{ __('citizen.complement.asked_on', ['date' => $complement->created_at->translatedFormat('d F Y')]) }}</p>
    </x-alert>

    {{-- Le motif de l'officier, cite tel qu'il l'a ecrit : c'est la seule
         chose qui dit au demandeur QUOI refaire. --}}
    <x-card :title="__('citizen.complement.officer_says')">
        <blockquote class="quote">{{ $complement->message }}</blockquote>
    </x-card>

    <x-card :title="__('citizen.complement.tips_title')">
        <ul>
            <li>{{ __('citizen.complement.tip_flat') }}</li>
            <li>{{ __('citizen.complement.tip_corners') }}</li>
            <li>{{ __('citizen.complement.tip_still') }}</li>
        </ul>
    </x-card>

    <x-card>
        @if ($errors->any())
            <x-alert variant="danger" :title="__('officer.action_impossible')">
                <ul class="alert__list">
                    @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('citizen.requests.complement.store', $demande) }}"
              enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label class="field__label" for="file">
                    {{ __('citizen.complement.file') }}
                    <span aria-hidden="true">*</span>
                    <span class="visually-hidden">({{ __('common.required_field') }})</span>
                </label>
                <span class="field__hint" id="file-hint">{{ __('citizen.complement.file_hint') }}</span>
                {{--
                    `capture` n'est PAS pose : il forcerait l'appareil photo et
                    priverait de la galerie une personne qui a deja pris la
                    photo, ou qui utilise un ordinateur. Le navigateur propose
                    les deux quand on le laisse faire.
                --}}
                <input class="field__control" type="file" id="file" name="file" required
                       accept="image/jpeg,image/png,application/pdf"
                       aria-describedby="file-hint @if ($errors->has('file')) file-error @endif"
                       @if ($errors->has('file')) aria-invalid="true" @endif>
                @error('file')<p class="field__error" id="file-error">{{ $message }}</p>@enderror
            </div>

            <x-button type="submit" variant="primary">{{ __('citizen.complement.send') }}</x-button>
            <x-button href="{{ route('citizen.requests.show', $demande) }}" variant="secondary">
                {{ __('common.cancel') }}
            </x-button>
        </form>
    </x-card>

    <x-card :title="__('citizen.complement.replaces_title')">
        <p>{{ __('citizen.complement.replaces_body') }}</p>
        <p>{{ __('citizen.complement.restarts_body') }}</p>
        <p class="u-note u-flush">{{ __('citizen.complement.no_old_photo') }}</p>
    </x-card>
@endsection
