@extends('layouts.app')
@section('title', __('citizen.requests_title'))

@section('content')
    {{--
        LA LISTE DES DEMANDES, REFONDUE (D-100).

        Elle était un tableau à cinq colonnes. La maquette du dossier cible en
        fait une liste de cartes, et ce n'est pas qu'une question de goût : sur
        un téléphone, un tableau se lit en empilant des paires
        intitulé/valeur — cinq lignes pour dire une demande. La carte dit la
        même chose en trois lignes, et met l'action au même endroit à chaque
        fois.
    --}}
    <x-page-hero :title="__('citizen.requests_title')" :lede="__('citizen.requests_lede')">
        {{--
            UN FORMULAIRE, PAS UN LIEN (D-071). `citizen.requests.start` CRÉE
            un brouillon : la route est un POST et doit le rester, sinon un
            préchargement de navigateur la déclencherait.
        --}}
        <form method="POST" action="{{ route('citizen.requests.start') }}">
            @csrf
            <x-button type="submit" variant="primary">
                {{ $requests->isEmpty() ? __('dashboard.citizen.apply') : __('dashboard.citizen.apply_again') }}
            </x-button>
        </form>
    </x-page-hero>

    <x-flash />

    @if ($total === 0)
        <x-card>
            <x-empty-state :title="__('citizen.requests_empty_title')">
                <p>{{ __('citizen.requests_empty_body') }}</p>
            </x-empty-state>
        </x-card>
    @else
        {{--
            LES ONGLETS SONT DES LIENS, et chacun porte son compte.

            Un onglet qui n'annonce pas combien il contient oblige à cliquer
            pour savoir s'il est vide. « Action requise (1) » se lit d'un coup
            d'œil, et c'est le seul onglet qui compte vraiment : il rassemble
            ce que le demandeur doit faire lui-même.
        --}}
        <nav class="filter-tabs" aria-label="{{ __('citizen.filter_aria') }}">
            @foreach ($onglets as $cle => $nombre)
                <a class="filter-tabs__tab @if ($filtre === $cle) is-current @endif"
                   href="{{ route('citizen.requests.index', $cle === 'all' ? [] : ['filtre' => $cle]) }}"
                   @if ($filtre === $cle) aria-current="page" @endif>
                    {{ __("citizen.filter_{$cle}") }}
                    <span class="filter-tabs__count">{{ $nombre }}</span>
                </a>
            @endforeach
        </nav>

        @if ($requests->isEmpty())
            <x-card>
                <x-empty-state :title="__('citizen.filter_none_title')">
                    <p>{{ __('citizen.filter_none_body') }}</p>
                </x-empty-state>
            </x-card>
        @else
            <ul class="request-cards">
                @foreach ($requests as $demande)
                    @php
                        $arrete = $demande->status->isStopped();
                        $rang = $demande->status->timelineRank();
                        // LA MEME DEFINITION QUE L'ONGLET « a vous de jouer ».
                        // Le liseré ne marquait que les compléments, alors que
                        // l'onglet compte AUSSI les brouillons : un brouillon
                        // était compté dans l'onglet sans être signalé dans la
                        // liste. Deux définitions du même mot, c'est une de
                        // trop.
                        $aVousDeJouer = $demande->isDraft() || $demande->pending_complements > 0;
                    @endphp
                    <li class="request-card @if ($aVousDeJouer) request-card--attention @endif">
                        <div class="request-card__head">
                            <div>
                                <h2 class="request-card__title">
                                    {{ __('citizen.card_title', ['nom' => $demande->full_name_at_birth ?: __('citizen.card_no_name')]) }}
                                </h2>
                                <p class="request-card__meta">
                                    <span>{{ $demande->reference }}</span>
                                    <span>{{ $demande->center?->name }}</span>
                                    <span>{{ $demande->submitted_at?->translatedFormat('d/m/Y') ?? __('citizen.card_not_sent') }}</span>
                                </p>
                            </div>
                            <div class="request-card__aside">
                                <x-status-badge :status="$demande->status" />
                                @if ($demande->isDraft())
                                    <a href="{{ route('citizen.requests.step', ['reissuanceRequest' => $demande, 'step' => min($demande->last_completed_step + 1, 4)]) }}">{{ __('common.resume') }}</a>
                                @elseif ($demande->pending_complements)
                                    <a href="{{ route('citizen.requests.complement', $demande) }}">{{ __('citizen.card_send_document') }}</a>
                                @else
                                    <a href="{{ route('citizen.requests.show', $demande) }}">{{ __('common.track') }}</a>
                                @endif
                            </div>
                        </div>

                        {{--
                            LA BARRE NE MENT PAS.

                            Un parcours ARRÊTÉ — rejeté ou annulé — n'a pas
                            d'avancement : `rejected`, `cancelled` et `signed`
                            partagent le rang 4, et s'en servir afficherait une
                            barre pleine sous « Rejetée » (D-029, D-087). Où
                            le dossier s'est exactement arrêté ne se lit que
                            dans le journal d'audit, donc pas ici sans une
                            requête par carte. On ne montre donc AUCUNE barre,
                            et on le dit en toutes lettres.

                            La barre est décorative : l'état est déjà écrit
                            dans la pastille, et la couleur ne porte jamais
                            seule une information.
                        --}}
                        @if ($arrete)
                            <p class="request-card__stopped">{{ __('citizen.card_stopped') }}</p>
                        @else
                            <div class="request-card__progress" aria-hidden="true">
                                <span class="request-card__bar" data-step="{{ $rang }}"></span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{ $requests->links('pagination') }}
        @endif
    @endif
@endsection
