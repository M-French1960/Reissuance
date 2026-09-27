@extends('layouts.public')
@section('title', __("public.legal.{$page}.title"))

@section('content')
    <h1>{{ __("public.legal.{$page}.title") }}</h1>

    {{--
        L'AVERTISSEMENT EST EN HAUT, ET IL EST FRANC (D-098).
        Cette page n'est pas le document juridique. La maquette du client était
        elle-même vide et renvoyait la rédaction à un juriste ; nous disons la
        même chose, en ajoutant ce qui est vérifiable dans le code plutôt que
        de laisser une page blanche.
    --}}
    <section class="public-card verdict verdict--ko" role="status" aria-labelledby="avertissement-titre">
        <h2 id="avertissement-titre">{{ __('public.legal.notice_title') }}</h2>
        <p class="u-flush">{{ __('public.legal.notice_body') }}</p>
    </section>

    <section class="public-card" aria-labelledby="faits-titre">
        <h2 id="faits-titre">{{ __('public.legal.facts_title') }}</h2>
        <p>{{ __("public.legal.{$page}.facts_intro") }}</p>

        <ul>
            @for ($i = 1; $i <= $faits; $i++)
                <li>{{ __("public.legal.{$page}.fact_{$i}") }}</li>
            @endfor
        </ul>

        <p class="u-note u-flush">{{ __('public.legal.facts_note') }}</p>
    </section>

    <section class="public-card" aria-labelledby="ouvert-titre">
        <h2 id="ouvert-titre">{{ __('public.legal.open_title') }}</h2>
        <p>{{ __("public.legal.{$page}.open_intro") }}</p>

        <ul>
            @for ($i = 1; $i <= $ouvert; $i++)
                <li>{{ __("public.legal.{$page}.open_{$i}") }}</li>
            @endfor
        </ul>
    </section>
@endsection
