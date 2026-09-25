@extends('layouts.app')
@section('title', __('dashboard.officer.title'))

@section('content')
    {{--
        LE POSTE DE L'OFFICIER (D-073, D-080).

        Composition reprise de la maquette « officer-dashboard ». Ce qui a
        change, et pourquoi :

          - la maquette affichait cinq compteurs et OUBLIAIT « en cours de
            vérification ». C'est pourtant le seul qui dit a un officier ce
            qu'il a lui-meme sur le feu. Il est ici ;
          - elle proposait un filtre « Centre : Tous ». La portee globale
            restreint un officier aux demandes de SON centre, et rien ne peut
            l'ouvrir. Proposer un tel filtre laisserait croire le contraire ;
          - « Rapports » et « Paramètres » dans le menu : aucune de ces deux
            pages n'existe, et les reglages sont reserves a l'administrateur ;
          - « Traiter la plus ancienne » ouvre la file triee par date de
            depot croissante. Il n'existe pas d'action « prendre en charge la
            plus ancienne » : la prise en charge est un POST sur un dossier
            precis, et l'inventer ici aurait attribue un dossier sans que
            l'officier l'ait vu.
    --}}
    <h1>{{ __('dashboard.officer.heading') }}</h1>

    <x-flash />

    <section class="dash-welcome" aria-labelledby="accueil-officier">
        <span class="dash-welcome__orb" aria-hidden="true"></span>
        <div class="dash-welcome__text">
            <h2 id="accueil-officier">{{ __('dashboard.officer.welcome', ['name' => auth()->user()->name]) }}</h2>
            <p>{{ __('dashboard.officer.welcome_lede') }}</p>
            <p class="u-note">{{ __('dashboard.officer.centre_line', ['centre' => auth()->user()->center?->name ?? __('common.none')]) }}</p>
        </div>
        <div class="dash-welcome__actions">
            <x-button variant="primary"
                      href="{{ route('officer.queue', ['statut' => \App\Enums\RequestStatus::Pending->value, 'tri' => 'submitted_at', 'sens' => 'asc']) }}">
                {{ __('dashboard.officer.oldest') }}
            </x-button>
        </div>
    </section>

    <section aria-labelledby="compteurs">
        <h2 id="compteurs" class="visually-hidden">{{ __('dashboard.officer.counters_title') }}</h2>

        {{-- Chaque compteur OUVRE la file filtree sur son statut : un nombre
             qu'on ne peut pas ouvrir ne sert a rien (D-073). --}}
        <ul class="dash-counters">
            @foreach ([
                \App\Enums\RequestStatus::Pending,
                \App\Enums\RequestStatus::UnderReview,
                \App\Enums\RequestStatus::AwaitingSignature,
                \App\Enums\RequestStatus::Escalated,
                \App\Enums\RequestStatus::Signed,
                \App\Enums\RequestStatus::Rejected,
            ] as $statut)
                <li>
                    <a class="dash-counter dash-counter--{{ $statut->tone() }}"
                       href="{{ route('officer.queue', ['statut' => $statut->value]) }}">
                        <span class="dash-counter__label">{{ $statut->label() }}</span>
                        <span class="dash-counter__value">{{ $counts[$statut->value] }}</span>
                        <span class="dash-counter__hint">{{ __('dashboard.officer.hint_'.$statut->value) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Dit une fois, clairement : ces chiffres ne sont pas ceux du pays. --}}
        <p class="u-note">{{ __('dashboard.officer.scope_note') }}</p>
    </section>

    <x-card :title="__('dashboard.officer.queue_title')">
        <p>{{ __('dashboard.officer.queue_body') }}</p>
        <div class="row-actions">
            <x-button href="{{ route('officer.queue') }}" variant="primary">{{ __('dashboard.officer.open_queue') }}</x-button>
            <x-button href="{{ route('notifications.index') }}" variant="secondary">{{ __('common.notifications') }}</x-button>
        </div>
    </x-card>
@endsection
