@extends('layouts.app')
@section('title', __('admin.centers.title'))

@section('content')
    <h1>{{ __('admin.centers.title') }}</h1>
    <p class="lede">{{ __('admin.centers.intro') }}</p>

    <x-flash />

    <x-card>
        <form method="GET" action="{{ route('admin.centers.index') }}" class="toolbar">
            <x-field name="recherche" :label="__('admin.centers.search')" :value="request('recherche')"
                     :hint="__('admin.centers.search_hint')" />
            <div class="field">
                <label class="field__label" for="raccordement">{{ __('admin.centers.connection') }}</label>
                <select class="field__control" id="raccordement" name="raccordement">
                    <option value="">{{ __('admin.centers.all') }}</option>
                    <option value="connected" @selected(request('raccordement') === 'connected')>{{ __('admin.centers.connected') }}</option>
                    <option value="disconnected" @selected(request('raccordement') === 'disconnected')>{{ __('admin.centers.disconnected') }}</option>
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('common.filter') }}</x-button>
            <x-button href="{{ route('admin.centers.create') }}" variant="primary">{{ __('admin.centers.connect_button') }}</x-button>
        </form>
    </x-card>

    @if ($centers->isEmpty())
        <x-card>
            <x-empty-state :title="request()->hasAny(['recherche', 'raccordement']) ? __('admin.centers.no_match_title') : __('admin.centers.empty_title')">
                {{ request()->hasAny(['recherche', 'raccordement']) ? __('admin.centers.no_match_body') : __('admin.centers.empty_body') }}
            </x-empty-state>
        </x-card>
    @else
        <ul class="centers" role="list" aria-label="{{ __('admin.centers.list_aria') }}">
            @foreach ($centers as $center)
                @php
                    $chiffres = $aggregates[$center->id] ?? null;
                    $agents = $officers[$center->id] ?? 0;
                    $maires = $mayors[$center->commune_id] ?? 0;
                    /*
                     * L'ANCIENNETE EST UN FAIT, PAS UN JUGEMENT. La maquette
                     * demandait un compteur « en retard » ; aucun delai de
                     * traitement n'est arbitre (question ouverte D8), et je ne
                     * declare pas un dossier tardif sur un seuil que j'aurais
                     * choisi moi-meme.
                     */
                    $jours = $chiffres?->oldest
                        ? (int) \Carbon\Carbon::parse($chiffres->oldest)->startOfDay()->diffInDays(now()->startOfDay())
                        : null;
                @endphp
                <li class="center-card @unless ($center->is_active) center-card--off @endunless">
                    <div class="center-card__head">
                        <div>
                            <h2 class="center-card__name">{{ $center->name }}</h2>
                            <p class="center-card__where">{{ $center->commune?->name }} · {{ $center->code }}</p>
                        </div>
                        <span class="badge badge--{{ $center->is_active ? "success" : "neutral" }}">
                            {{ $center->is_active ? __('admin.centers.connected') : __('admin.centers.disconnected') }}
                        </span>
                    </div>

                    {{--
                        Les deux pieges qu'un administrateur ne peut pas voir
                        autrement : un centre raccorde sans agent actif, et une
                        commune sans maire actif. Le premier laisse les dossiers
                        sans examen, le second produit des actes que personne ne
                        peut signer. Ils sont ecrits sur la carte, et non
                        annonces une fois dans une notification qui disparait.
                    --}}
                    @if ($center->is_active && $agents === 0)
                        <p class="center-card__warn">{{ __('admin.centers.no_officer') }}</p>
                    @endif
                    @if ($center->is_active && $maires === 0)
                        <p class="center-card__warn">{{ __('admin.centers.no_mayor') }}</p>
                    @endif

                    <dl class="center-card__nums">
                        <div>
                            <dt>{{ __('admin.centers.officers') }}</dt>
                            <dd>{{ $agents }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('admin.centers.in_flight') }}</dt>
                            <dd>{{ $chiffres?->in_flight ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('admin.centers.oldest') }}</dt>
                            <dd>
                                @if ($jours === null)
                                    <span class="center-card__none">{{ __('admin.centers.oldest_none') }}</span>
                                @else
                                    {{ trans_choice('admin.centers.oldest_days', $jours, ['count' => $jours]) }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="center-card__foot">
                        <x-button href="{{ route('admin.centers.edit', $center) }}" variant="secondary">
                            {{ __('admin.centers.edit') }}
                        </x-button>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $centers->links() }}
    @endif

    <x-card :title="__('admin.centers.what_title')">
        <p>{{ __('admin.centers.what_body') }}</p>
        <p>{{ __('admin.centers.connected_note') }}</p>
        <p>{{ __('admin.centers.disconnected_note') }}</p>
    </x-card>
@endsection
