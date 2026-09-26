@extends('layouts.public')
@section('title', __('public.verify.title'))

@section('content')
    {{--
        VÉRIFIER L'AUTHENTICITÉ D'UN ACTE, SANS COMPTE (D-088).

        Celui qui verifie n'est pas le demandeur : c'est l'administration,
        l'école ou l'ambassade qui REÇOIT une copie, et qui a le papier sous
        les yeux. La réponse sert à COMPARER, pas à renseigner.
    --}}
    <h1>{{ __('public.verify.title') }}</h1>
    <p class="lede">{{ __('public.verify.lede') }}</p>

    <section class="public-card" aria-labelledby="verifier-titre">
        <h2 id="verifier-titre" class="visually-hidden">{{ __('public.verify.code') }}</h2>

        <form method="POST" action="{{ route('verify.check') }}" class="verify-form">
            @csrf
            <div class="field">
                <label class="field__label" for="code">{{ __('public.verify.code') }}</label>
                <span class="field__hint" id="code-hint">{{ __('public.verify.code_hint') }}</span>
                {{--
                    `autocapitalize` et `spellcheck` : un code se tape en
                    majuscules et n'est pas un mot. Sans cela, le clavier d'un
                    téléphone le corrige et propose autre chose.
                    Aucun formatage en JavaScript : le contrôleur normalise ce
                    qui arrive, donc la page fonctionne sans script.
                --}}
                <input class="field__control" type="text" id="code" name="code"
                       value="{{ $codeSaisi }}"
                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                       inputmode="latin" maxlength="32" required
                       aria-describedby="code-hint">
            </div>
            <button type="submit" class="home-btn home-btn--primary">{{ __('public.verify.check') }}</button>
        </form>
    </section>

    @if ($resultat !== null)
        <section class="public-card verdict verdict--{{ $resultat === false ? 'ko' : 'ok' }}"
                 aria-labelledby="verdict-titre" tabindex="-1" role="status">
            @if ($resultat === false)
                <h2 id="verdict-titre">{{ __('public.verify.unknown_title') }}</h2>
                <p>{{ __('public.verify.unknown_body') }}</p>
            @else
                <h2 id="verdict-titre">{{ __('public.verify.genuine_title') }}</h2>
                <p>{{ __('public.verify.genuine_body') }}</p>

                <dl class="verdict__facts">
                    <div>
                        <dt>{{ __('public.verify.reference') }}</dt>
                        <dd>{{ $resultat['reference'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('public.verify.issued_on') }}</dt>
                        <dd>{{ $resultat['delivre'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('public.verify.centre') }}</dt>
                        <dd>{{ $resultat['centre'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('public.verify.initials') }}</dt>
                        <dd>{{ $resultat['initiales'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('public.verify.birth_year') }}</dt>
                        <dd>{{ $resultat['annee'] }}</dd>
                    </div>
                </dl>

                {{-- Un acte produit par l'adaptateur de démonstration est
                     authentique au sens où ce service l'a délivré, et n'est pas
                     un acte juridique. Le dire ici, et pas seulement sur le
                     PDF (D-025, §10). --}}
                @unless ($resultat['valeurJuridique'])
                    <div class="verdict__warning">
                        <strong>{{ __('public.verify.demo_title') }}</strong>
                        <p class="u-flush">{{ __('public.verify.demo_body') }}</p>
                    </div>
                @endunless
            @endif
        </section>
    @endif

    <section class="public-notes">
        <p>{{ __('public.verify.privacy_note') }}</p>
        <p>{{ __('public.verify.no_revocation') }}</p>
        <p>{{ __('public.verify.not_printed') }}</p>
    </section>
@endsection
