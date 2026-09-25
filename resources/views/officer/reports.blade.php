@extends('layouts.app')
@section('title', __('officer.reports.title'))

@section('content')
    {{--
        RAPPORTS DE L'OFFICIER (D-084).

        POURQUOI DU SVG SANS viewBox, ET NON DES BLOCS AVEC UNE HAUTEUR EN
        POURCENTAGE.

        La premiere version ecrivait `style="height: 25%"` sur chaque barre.
        Elle rendait 200, et toutes les barres mesuraient 3 px. La politique
        de securite declare `style-src 'self'` sans `unsafe-inline` : le
        navigateur REFUSE les attributs style=, et la console le disait. Un
        graphique faux, sur une page valide.

        Un SVG sans viewBox resout ses attributs de geometrie en pourcentage
        contre sa propre taille : rien ne se deforme, le rayon des coins reste
        en pixels reels, et surtout ces attributs ne sont pas des styles — la
        CSP ne les touche pas. Le texte, lui, reste du HTML a sa taille : a
        320 px un viewBox l'aurait ramene sous 8 px.

        Aucun JavaScript : une bibliotheque de graphiques serait refusee par la
        meme politique.

        L'IDENTITE N'EST JAMAIS PORTEE PAR LA COULEUR SEULE : legende, valeurs
        ecrites, infobulle native sur chaque segment, et le meme tableau de
        chiffres juste dessous.
    --}}
    <h1>{{ __('officer.reports.title') }}</h1>

    <p>{{ __('officer.reports.lede', ['centre' => auth()->user()->center?->name ?? __('common.none')]) }}</p>
    <p class="u-note">{{ __('officer.reports.scope_note') }}</p>

    <x-flash />

    <x-card>
        <form method="GET" action="{{ route('officer.reports') }}" class="report-period">
            <div class="field">
                <label class="field__label" for="semaines">{{ __('officer.reports.period') }}</label>
                <select class="field__control" id="semaines" name="semaines">
                    @foreach ($periodes as $choix)
                        <option value="{{ $choix }}" @selected($choix === $semaines)>
                            {{ __('officer.reports.weeks', ['count' => $choix]) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('officer.reports.apply') }}</x-button>
        </form>
        <p class="u-note u-flush">{{ __('officer.reports.since', ['date' => $debut->translatedFormat('d F Y')]) }}</p>
    </x-card>

    @php
        $maximum = max(1, max(array_map(fn (array $s): int => $s['total'], $series)));
        $totalPeriode = array_sum(array_map(fn (array $s): int => $s['total'], $series));
    @endphp

    <x-card :title="__('officer.reports.decisions_title')">
        @if ($totalPeriode === 0)
            <p class="u-note u-flush">{{ __('officer.reports.decisions_empty') }}</p>
        @else
            {{-- La legende precede le graphique : elle en donne la cle avant
                 qu'on essaie de le lire. --}}
            <ul class="chart-legend">
                @foreach ($types as $type)
                    <li>
                        <span class="chart-legend__mark chart-legend__mark--{{ $type->value }}" aria-hidden="true"></span>
                        {{ $type->label() }}
                    </li>
                @endforeach
            </ul>

            {{-- aria-hidden : le graphique est une mise en image du tableau qui
                 suit. Le faire lire deux fois n'apporte rien et double la
                 longueur pour une personne qui l'entend. --}}
            <div class="chart" aria-hidden="true">
                @foreach ($series as $semaine)
                    <div class="chart__column">
                        <span class="chart__value">{{ $semaine['total'] ?: '' }}</span>
                        <svg class="chart__bars" preserveAspectRatio="none" focusable="false">
                            @php $cumul = 0; @endphp
                            @foreach ($types as $index => $type)
                                @php
                                    $n = $semaine['parType'][$type->value] ?? 0;
                                    $part = $n / $maximum * 100;
                                    $cumul += $part;
                                @endphp
                                @if ($n > 0)
                                    {{-- L'origine d'un SVG est en haut : une barre qui monte
                                         de `cumul` part donc a 100 - cumul. --}}
                                    <rect class="chart__rect chart__rect--{{ $type->value }}"
                                          x="0" width="100%"
                                          y="{{ round(100 - $cumul, 2) }}%"
                                          height="{{ round($part, 2) }}%"
                                          @if ($loop->last || $cumul >= 99.99) rx="4" @endif>
                                        {{-- Infobulle native, sans une ligne de script. --}}
                                        <title>{{ $type->label() }} : {{ $n }}</title>
                                    </rect>
                                @endif
                            @endforeach
                        </svg>
                        <span class="chart__tick">{{ $semaine['debut']->translatedFormat('d/m') }}</span>
                    </div>
                @endforeach
            </div>

            <details class="chart-table">
                <summary>{{ __('officer.reports.table_view') }}</summary>
                <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('officer.reports.decisions_title') }}">
                    <table>
                        <caption class="visually-hidden">{{ __('officer.reports.decisions_title') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('officer.reports.week_column') }}</th>
                                @foreach ($types as $type)
                                    <th scope="col">{{ $type->label() }}</th>
                                @endforeach
                                <th scope="col">{{ __('officer.reports.total_column') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($series as $semaine)
                                <tr>
                                    <th scope="row" data-label="{{ __('officer.reports.week_column') }}">
                                        {{ __('officer.reports.week_of', ['date' => $semaine['debut']->translatedFormat('d/m/Y')]) }}
                                    </th>
                                    @foreach ($types as $type)
                                        <td data-label="{{ $type->label() }}">{{ $semaine['parType'][$type->value] ?? 0 }}</td>
                                    @endforeach
                                    <td data-label="{{ __('officer.reports.total_column') }}">{{ $semaine['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </x-card>

    <x-card :title="__('officer.reports.reasons_title')">
        @if ($motifs === [])
            <p class="u-note u-flush">{{ __('officer.reports.reasons_empty') }}</p>
        @else
            @php $pic = max(1, max(array_map(fn (array $m): int => $m['total'], $motifs))); @endphp

            {{-- Une seule serie : pas de legende, le titre de la carte la
                 nomme. La longueur porte la grandeur, la couleur ne dit rien
                 de plus et reste donc la meme pour toutes les barres. --}}
            <ul class="rank">
                @foreach ($motifs as $motif)
                    <li class="rank__item">
                        <span class="rank__label">
                            {{ $motif['libelle'] }}
                            <strong>{{ $motif['total'] }}</strong>
                        </span>
                        <svg class="rank__track" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                            <rect class="rank__rail" x="0" y="0" width="100%" height="100%" rx="6"></rect>
                            <rect class="rank__bar @unless ($motif['prerempli']) rank__bar--other @endunless"
                                  x="0" y="0" height="100%" rx="6"
                                  width="{{ round($motif['total'] / $pic * 100, 2) }}%"></rect>
                        </svg>
                    </li>
                @endforeach
            </ul>

            <p class="u-note">{{ __('officer.reports.other_note') }}</p>
        @endif
    </x-card>
@endsection
