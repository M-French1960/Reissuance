@extends('layouts.public')
@section('title', __('public.track.title'))

@section('content')
    {{--
        SUIVRE UNE DEMANDE SANS COMPTE (D-096).

        Deux facteurs sont exigés : la référence ET les quatre derniers
        chiffres du téléphone donné lors de la demande. La référence seule a
        été refusée deux fois — elle ouvrirait le dossier d'un inconnu à qui
        la devine.
    --}}
    <h1>{{ __('public.track.title') }}</h1>
    <p class="lede">{{ __('public.track.lede') }}</p>

    <section class="public-card" aria-labelledby="suivi-titre">
        <h2 id="suivi-titre" class="visually-hidden">{{ __('public.track.form_title') }}</h2>

        <form method="POST" action="{{ route('track.check') }}">
            @csrf
            <div class="track-form__row">
                <div class="field">
                    <label class="field__label" for="reference">{{ __('public.track.reference') }}</label>
                    <span class="field__hint" id="reference-hint">{{ __('public.track.reference_hint') }}</span>
                    {{--
                        Aucun formatage en JavaScript : le contrôleur normalise
                        ce qui arrive, donc la page fonctionne sans script.
                    --}}
                    <input class="field__control" type="text" id="reference" name="reference"
                           value="{{ $referenceSaisie }}"
                           autocomplete="off" autocapitalize="characters" spellcheck="false"
                           inputmode="latin" maxlength="32" required
                           aria-describedby="reference-hint">
                </div>
                <div class="field">
                    <label class="field__label" for="telephone">{{ __('public.track.phone') }}</label>
                    <span class="field__hint" id="telephone-hint">{{ __('public.track.phone_hint') }}</span>
                    {{-- `inputmode="numeric"` ouvre le pavé numérique sur un
                         téléphone : quatre chiffres ne se tapent pas au clavier
                         alphabétique. --}}
                    <input class="field__control" type="text" id="telephone" name="telephone"
                           autocomplete="off" inputmode="numeric" pattern="[0-9]*"
                           maxlength="4" required
                           aria-describedby="telephone-hint">
                </div>
            </div>
            <button type="submit" class="home-btn home-btn--primary">{{ __('public.track.submit') }}</button>
        </form>
    </section>

    @if ($resultat !== null)
        <section class="public-card verdict verdict--{{ $resultat === false ? 'ko' : 'ok' }}"
                 aria-labelledby="resultat-titre" tabindex="-1" role="status">
            @if ($resultat === false)
                {{--
                    UNE SEULE RÉPONSE D'ÉCHEC, quelle qu'en soit la cause :
                    référence inconnue, mal formée, téléphone faux, ou dossier
                    non suivable. Distinguer ces cas ferait de cette page un
                    oracle à références.
                --}}
                <h2 id="resultat-titre">{{ __('public.track.unknown_title') }}</h2>
                <p>{{ __('public.track.unknown_body') }}</p>
            @else
                <h2 id="resultat-titre">{{ __('public.track.found_title', ['reference' => $resultat['reference']]) }}</h2>

                <p>{{ $resultat['message'] }}</p>

                <ol class="track-steps">
                    @foreach ($resultat['jalons'] as $jalon)
                        <li class="track-steps__step track-steps__step--{{ $jalon['etat'] }}">
                            <span class="track-steps__dot" aria-hidden="true">
                                {{ $jalon['etat'] === 'fait' ? '✓' : $loop->iteration }}
                            </span>
                            <span class="track-steps__label">
                                {{ $jalon['titre'] }}
                                {{-- L'état en toutes lettres : la couleur ne
                                     porte jamais seule une information. --}}
                                <span class="track-steps__state">{{ __('public.track.state_'.$jalon['etat']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                <p class="u-note u-flush">{{ __('public.track.sign_in_note') }}</p>
            @endif
        </section>
    @endif
@endsection
