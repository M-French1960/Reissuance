@extends('layouts.app')
@section('title', __('mayor.signed.title'))

@section('content')
    {{--
        CE QUE LE MAIRE A SIGNE (D-086).

        Le graphique reprend la technique de D-084 : un SVG SANS viewBox, dont
        les attributs de geometrie sont des pourcentages. La politique de
        securite declare `style-src 'self'` sans `unsafe-inline` — un
        `style="height: …"` serait refuse en silence et rendrait un graphique
        plat sur une page qui repond 200. Les attributs SVG ne sont pas des
        styles : la politique ne les touche pas.

        AUCUNE COLONNE « DELIVRANCE ». La maquette en porte une. Cette notion
        n'existe pas dans le modele de donnees, et l'inventer afficherait un
        etat faux a cote d'un acte signe.
    --}}
    <h1>{{ __('mayor.signed.title') }}</h1>
    <p class="lede">{{ __('mayor.signed.lede') }}</p>

    <x-flash />

    <x-card>
        <form method="GET" action="{{ route('mayor.signed') }}" class="toolbar">
            <x-field name="recherche" :label="__('mayor.signed.search')" :value="request('recherche')"
                     :hint="__('mayor.signed.search_hint')" />
            <div class="field">
                <label class="field__label" for="semaines">{{ __('mayor.signed.period') }}</label>
                <select class="field__control" id="semaines" name="semaines">
                    @foreach ($periodes as $choix)
                        <option value="{{ $choix }}" @selected($choix === $semaines)>
                            {{ __('mayor.signed.weeks', ['count' => $choix]) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('common.filter') }}</x-button>
        </form>
        <p class="u-note u-flush">{{ __('mayor.signed.since', ['date' => $debut->translatedFormat('d F Y')]) }}</p>
    </x-card>

    @php
        $maximum = max(1, max(array_map(fn (array $s): int => $s['total'], $series)));
    @endphp

    <x-card :title="__('mayor.signed.chart_title')">
        @if ($total === 0)
            <p class="u-note u-flush">{{ __('mayor.signed.chart_empty') }}</p>
        @else
            {{-- aria-hidden : le graphique met en image le tableau qui suit.
                 Le faire lire deux fois double la longueur pour rien. --}}
            <div class="chart" aria-hidden="true">
                @foreach ($series as $semaine)
                    <div class="chart__column">
                        <span class="chart__value">{{ $semaine['total'] ?: '' }}</span>
                        <svg class="chart__bars" preserveAspectRatio="none" focusable="false">
                            @if ($semaine['total'] > 0)
                                @php $part = $semaine['total'] / $maximum * 100; @endphp
                                {{-- L'origine d'un SVG est en haut : une barre qui monte
                                     de `part` commence donc a 100 - part. --}}
                                <rect class="chart__rect chart__rect--signed"
                                      x="0" width="100%" rx="4"
                                      y="{{ round(100 - $part, 2) }}%"
                                      height="{{ round($part, 2) }}%">
                                    <title>{{ trans_choice('mayor.signed.acts_count', $semaine['total'], ['count' => $semaine['total']]) }}</title>
                                </rect>
                            @endif
                        </svg>
                        <span class="chart__tick">{{ $semaine['debut']->translatedFormat('d/m') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <dl class="kv">
            <div>
                <dt>{{ __('mayor.signed.total_period') }}</dt>
                <dd>{{ $total }}</dd>
            </div>
            <div>
                <dt>{{ __('mayor.signed.returned') }}</dt>
                <dd>{{ $retournees }}</dd>
            </div>
            <div>
                <dt>{{ __('mayor.signed.average_delay') }}</dt>
                <dd>
                    @if ($delaiMoyen === null)
                        <span class="u-note">{{ __('mayor.signed.average_delay_none') }}</span>
                    @else
                        {{-- Le separateur decimal suit la langue lue : « 9,9 jours »
                             en francais, « 9.9 days » en anglais. Ecrit brut, le
                             nombre PHP sortait avec un point dans les deux langues. --}}
                        {{ trans_choice('mayor.signed.days', (int) ceil($delaiMoyen), [
                            'count' => \Illuminate\Support\Number::format($delaiMoyen, maxPrecision: 1, locale: app()->getLocale()),
                        ]) }}
                    @endif
                </dd>
            </div>
        </dl>

        {{-- Une mesure, pas un indicateur : aucune cible n'est opposee a ce
             delai, parce qu'aucun delai de traitement n'est arbitre (D8). --}}
        <p class="u-note u-flush">{{ __('mayor.signed.delay_note') }}</p>
    </x-card>

    <x-card :title="__('mayor.signed.history_title')">
        @if ($signatures->isEmpty())
            <x-empty-state :title="request()->filled('recherche') ? __('mayor.signed.no_match_title') : __('mayor.signed.empty_title')">
                {{ request()->filled('recherche') ? __('mayor.signed.no_match_body') : __('mayor.signed.empty_body') }}
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('mayor.signed.history_title') }}">
                <table>
                    <caption class="visually-hidden">{{ __('mayor.signed.history_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">{{ __('mayor.signed.holder') }}</th>
                            <th scope="col">{{ __('mayor.signed.signed_on') }}</th>
                            <th scope="col">{{ __('mayor.signed.centre') }}</th>
                            <th scope="col">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($signatures as $signature)
                            <tr>
                                <td data-label="{{ __('common.reference') }}">{{ $signature->request?->reference }}</td>
                                <td data-label="{{ __('mayor.signed.holder') }}">{{ $signature->request?->full_name_at_birth }}</td>
                                <td data-label="{{ __('mayor.signed.signed_on') }}">
                                    {{ $signature->signed_at?->translatedFormat('d/m/Y H:i') }}
                                </td>
                                <td data-label="{{ __('mayor.signed.centre') }}">{{ $signature->request?->center?->name }}</td>
                                <td data-label="{{ __('common.actions') }}">
                                    <span class="cell-actions">
                                        <a href="{{ route('acts.document', $signature) }}">{{ __('mayor.signed.download_act') }}</a>
                                        <a href="{{ route('acts.proof', $signature) }}">{{ __('mayor.signed.download_proof') }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $signatures->links() }}
        @endif
    </x-card>

    <x-card :title="__('mayor.signed.limits_title')">
        <p>{{ __('mayor.signed.limits_delivery') }}</p>
        <p>{{ __('mayor.signed.limits_export') }}</p>
        <p>{{ __('mayor.signed.limits_identity') }}</p>
    </x-card>
@endsection
