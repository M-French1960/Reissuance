@extends('layouts.public')
@section('title', __('public.help.title'))

@section('content')
    <h1>{{ __('public.help.title') }}</h1>
    <p class="lede">{{ __('public.help.lede') }}</p>

    <section class="public-card" aria-labelledby="questions-titre">
        <h2 id="questions-titre">{{ __('home.faq_title') }}</h2>

        {{--
            LE FILTRE EST UN ENRICHISSEMENT, PAS UNE CONDITION.
            Sans JavaScript, le champ ne fait rien et les cinq questions
            restent toutes lisibles : la page n'a jamais besoin du script pour
            rendre service. C'est la même règle que partout ailleurs ici.
        --}}
        <div class="field" data-help-filter-wrap hidden>
            <label class="field__label" for="filtre">{{ __('public.help.filter') }}</label>
            <input class="field__control" type="search" id="filtre"
                   autocomplete="off" data-help-filter>
        </div>

        <div class="home-faq">
            @foreach ($questions as $question)
                <details class="home-glass" data-help-item>
                    <summary>{{ __("home.faq_q{$question}") }}</summary>
                    <p>{{ __("home.faq_a{$question}") }}</p>
                </details>
            @endforeach
        </div>

        <p class="u-note" data-help-empty hidden>{{ __('public.help.no_match') }}</p>
    </section>

    {{--
        NOUS ÉCRIRE : ce que la maquette prévoyait, et pourquoi ce n'est pas
        un formulaire. Voir D-097.
    --}}
    <section class="public-card" aria-labelledby="contact-titre">
        <h2 id="contact-titre">{{ __('public.help.contact_title') }}</h2>
        <p>{{ __('public.help.contact_intro') }}</p>

        <ul class="help-channels">
            <li>{!! __('public.help.contact_thread', ['lien' => '<a href="'.e(route('login')).'">'.e(__('common.sign_in')).'</a>']) !!}</li>
            <li>{!! __('public.help.contact_track', ['lien' => '<a href="'.e(route('track.show')).'">'.e(__('public.track.title')).'</a>']) !!}</li>
            <li>{{ __('public.help.contact_centre') }}</li>
        </ul>

        <p class="u-note u-flush">{{ __('public.help.contact_no_form') }}</p>
    </section>
@endsection
