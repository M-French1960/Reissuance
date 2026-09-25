@extends('layouts.app')
@section('title', __('dashboard.citizen.title'))

@section('content')
    {{--
        L'ECRAN SUR LEQUEL LE CITOYEN ARRIVE (D-073, D-079).

        Composition reprise de la maquette fournie. Ce qui n'a pas ete repris,
        et pourquoi :

          - « Vous serez prevenu par SMS et e-mail » : l'application n'envoie
            aucun SMS, ses canaux sont la base et le courriel ;
          - « Declaration de perte » dans les pieces a preparer : le formulaire
            ne demande qu'une piece d'identite et une photo du demandeur ;
          - « Numero de telephone actif — pour recevoir le code de suivi » : le
            telephone est bien collecte au profil, mais rien ne lui envoie quoi
            que ce soit ;
          - le paiement comme deuxieme etape de la frise : l'encaissement est
            un reglage a trois valeurs, et le coder en dur ferait mentir la
            frise dans deux cas sur trois. Les paiements ont leur panneau ;
          - `<a href="form.html">Nouvelle demande</a>` : creer un brouillon
            est un POST, et un lien serait suivi par les prefetcheurs ;
          - le mot de passe range dans localStorage par le script de la
            maquette : jamais.
    --}}
    <h1>{{ __('dashboard.citizen.heading') }}</h1>

    <x-flash />

    @unless ($profilComplet)
        {{-- Said before they run into it: submitting needs a complete profile. --}}
        <x-alert variant="attention" title="{{ __('dashboard.citizen.profile_incomplete_title') }}">
            <p>{{ __('dashboard.citizen.profile_incomplete_body') }}</p>
            <div class="row-actions">
                <x-button href="{{ route('citizen.profile.edit') }}" variant="primary">{{ __('dashboard.citizen.complete_profile') }}</x-button>
            </div>
        </x-alert>
    @endunless

    @php $prenom = auth()->user()->profile?->first_name; @endphp
    <section class="dash-welcome" aria-labelledby="bienvenue">
        <span class="dash-welcome__orb" aria-hidden="true"></span>
        <div class="dash-welcome__text">
            <h2 id="bienvenue">
                {{ $prenom ? __('dashboard.citizen.welcome_named', ['name' => $prenom]) : __('dashboard.citizen.welcome_anonymous') }}
            </h2>
            <p>{{ __('dashboard.citizen.welcome_lede') }}</p>
        </div>
        <div class="dash-welcome__actions">
            {{--
                LE LIBELLE DIT CE QUE LE BOUTON FAIT.

                `citizen.requests.start` REPREND le brouillon en cours quand il
                y en a un, et n'en cree un que sinon. Annoncer « Faire une
                demande » a quelqu'un qui a deja une demande a mi-chemin lui
                ferait croire qu'il en ouvre une deuxieme, et il hesiterait a
                cliquer. Les deux appels a l'action de cet ecran portent donc
                le meme libelle, et c'est le brouillon qui le decide.

                Un POST, jamais un lien : il ecrit en base.
            --}}
            <form method="POST" action="{{ route('citizen.requests.start') }}">
                @csrf
                <x-button type="submit" variant="primary">
                    {{ $brouillon ? __('dashboard.citizen.resume_draft') : __('dashboard.citizen.apply') }}
                </x-button>
            </form>
        </div>
    </section>

    <div class="shell-grid">
        <div class="panel span-4 dash-stat">
            <span class="dash-stat__label">{{ __('dashboard.citizen.stat_active') }}</span>
            <span class="dash-stat__icon dash-stat__icon--wait" aria-hidden="true"><x-icon name="clock" /></span>
            <span class="dash-stat__value">{{ $enCours }}</span>
            <a class="dash-stat__link" href="{{ route('citizen.requests.index') }}">{{ __('dashboard.citizen.stat_see_all') }}</a>
        </div>

        {{--
            « ACTES DELIVRES », ET NON « DEMANDES TERMINEES ».

            Trois etats terminent un parcours : signe, refuse, annule. Les
            compter ensemble ferait lire « 1 demande terminee » a quelqu'un
            dont la demande vient d'etre REFUSEE. Ce compteur ne compte que les
            actes reellement delivres, et son libelle le dit.
        --}}
        <div class="panel span-4 dash-stat">
            <span class="dash-stat__label">{{ __('dashboard.citizen.stat_delivered') }}</span>
            <span class="dash-stat__icon dash-stat__icon--ok" aria-hidden="true"><x-icon name="check" /></span>
            <span class="dash-stat__value">{{ $delivres }}</span>
            <a class="dash-stat__link" href="{{ route('citizen.requests.index') }}">{{ __('dashboard.citizen.stat_see_all') }}</a>
        </div>

        <section class="span-4 dash-action" aria-labelledby="agir">
            <span class="dash-action__icon" aria-hidden="true"><x-icon name="id" :size="24" /></span>
            <h2 id="agir">{{ __('dashboard.citizen.action_title') }}</h2>
            <p>{{ __('dashboard.citizen.action_body') }}</p>
            <form method="POST" action="{{ route('citizen.requests.start') }}">
                @csrf
                <x-button type="submit" variant="primary">
                    {{ $brouillon ? __('dashboard.citizen.resume_draft') : __('dashboard.citizen.apply') }}
                </x-button>
            </form>
        </section>

        <section class="panel span-8" aria-labelledby="suivi">
            <div class="panel__head">
                <h2 id="suivi">{{ __('dashboard.citizen.track_title') }}</h2>
                @if ($suivie)
                    <a href="{{ route('citizen.requests.show', $suivie) }}">{{ __('dashboard.citizen.track_open') }}</a>
                @endif
            </div>

            @if ($suivie)
                <dl class="dash-meta">
                    <div><dt>{{ __('dashboard.citizen.track_reference') }}</dt><dd>{{ $suivie->reference }}</dd></div>
                    @if ($suivie->center)
                        <div><dt>{{ __('dashboard.citizen.track_centre') }}</dt><dd>{{ $suivie->center->name }}</dd></div>
                    @endif
                    @if ($suivie->submitted_at)
                        <div><dt>{{ __('dashboard.citizen.track_submitted') }}</dt><dd>{{ $suivie->submitted_at->translatedFormat('d F Y') }}</dd></div>
                    @endif
                </dl>

                {{--
                    LA MEME FRISE QUE LA PAGE DE SUIVI, pas une deuxieme.
                    Elle se construit sur le journal d'audit, seule trace de ce
                    qui a reellement eu lieu ; une version simplifiee deduite
                    du statut courant referait le defaut de D-075, qui
                    annoncait des etapes jamais franchies sur un dossier
                    annule.
                --}}
                <ol class="dash-tracker">
                    @foreach ($frise as $index => $jalon)
                        <li class="dash-tracker__step dash-tracker__step--{{ $jalon['etat'] }}"
                            @if ($jalon['etat'] === 'en_cours') aria-current="step" @endif>
                            <span class="dash-tracker__dot" aria-hidden="true">
                                @if ($jalon['etat'] === 'fait')
                                    <x-icon name="check" :size="14" />
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <span class="dash-tracker__body">
                                <strong>{{ $jalon['titre'] }}</strong>
                                @if ($jalon['detail'])
                                    <small>{{ $jalon['detail'] }}</small>
                                @endif
                                @if ($jalon['date'])
                                    <small>{{ $jalon['date'] }}</small>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-empty-state :title="__('dashboard.citizen.empty_title')">
                    <p>{{ __('dashboard.citizen.track_empty') }}</p>
                    <form method="POST" action="{{ route('citizen.requests.start') }}">
                        @csrf
                        <x-button type="submit" variant="primary">{{ __('dashboard.citizen.apply') }}</x-button>
                    </form>
                </x-empty-state>
            @endif
        </section>

        <section class="panel span-4" aria-labelledby="preparer">
            <div class="panel__head"><h2 id="preparer">{{ __('dashboard.citizen.checklist_title') }}</h2></div>
            {{-- Les deux pieces que le formulaire demande reellement, plus ce
                 qu'il faut avoir sous les yeux. Rien d'autre. --}}
            <ul class="dash-checklist">
                @foreach (['id', 'photo', 'details', 'email'] as $element)
                    <li>
                        <span class="dash-checklist__tick" aria-hidden="true"><x-icon name="check" :size="14" /></span>
                        {{ __("dashboard.citizen.checklist_{$element}") }}
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="panel span-5" aria-labelledby="activite">
            <div class="panel__head">
                <h2 id="activite">{{ __('dashboard.citizen.activity_title') }}</h2>
                <a href="{{ route('notifications.index') }}">{{ __('dashboard.citizen.activity_all') }}</a>
            </div>

            @forelse ($activite as $notification)
                @if ($loop->first)<ul class="dash-activity">@endif
                    <li>
                        <span class="dash-activity__icon" aria-hidden="true"><x-icon name="bell" :size="16" /></span>
                        <span>
                            <strong>{{ $notification->data['title'] ?? __('notifications.title') }}</strong>
                            @isset($notification->data['reference'])
                                <small>{{ $notification->data['reference'] }}</small>
                            @endisset
                        </span>
                        <time datetime="{{ $notification->created_at->toDateString() }}">
                            {{ $notification->created_at->translatedFormat('d/m/Y') }}
                        </time>
                    </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="u-note u-flush">{{ __('dashboard.citizen.activity_empty') }}</p>
            @endforelse
        </section>

        <section class="panel span-7" aria-labelledby="paiements">
            <div class="panel__head">
                <h2 id="paiements">{{ __('dashboard.citizen.payments_title') }}</h2>
                <a href="{{ route('citizen.requests.index') }}">{{ __('dashboard.citizen.payments_all') }}</a>
            </div>

            @if ($paiements->isEmpty())
                <p class="u-note u-flush">{{ __('dashboard.citizen.payments_empty') }}</p>
            @else
                <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('dashboard.citizen.payments_title') }}">
                    <table>
                        <caption class="visually-hidden">{{ __('dashboard.citizen.payments_title') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('dashboard.citizen.payments_request') }}</th>
                                <th scope="col">{{ __('dashboard.citizen.payments_amount') }}</th>
                                <th scope="col">{{ __('dashboard.citizen.payments_method') }}</th>
                                <th scope="col">{{ __('dashboard.citizen.payments_date') }}</th>
                                <th scope="col">{{ __('dashboard.citizen.payments_status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paiements as $paiement)
                                <tr>
                                    <td>{{ $paiement->request?->reference }}</td>
                                    {{-- Le montant tel qu'il a ete encaisse, jamais le tarif
                                         d'aujourd'hui : un recu ne se reecrit pas. --}}
                                    <td>{{ $paiement->money()->format() }}</td>
                                    <td>{{ $paiement->operator?->label() }}</td>
                                    <td>{{ $paiement->created_at->translatedFormat('d/m/Y') }}</td>
                                    <td><span class="badge badge--{{ $paiement->isPaid() ? 'success' : 'waiting' }}">{{ $paiement->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
