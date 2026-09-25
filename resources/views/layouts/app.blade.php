<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- The prototype left this out on 3 pages in 6, so mobile browsers laid
         them out in a ~980px virtual viewport. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', __('common.brand')) | {{ __('common.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @auth
        {{-- La coquille ne sert qu'aux ecrans connectes : une page de
             connexion n'a pas de barre laterale a habiller. --}}
        <link rel="stylesheet" href="{{ asset('css/shell.css') }}">
        <script src="{{ asset('js/app-shell.js') }}" defer></script>
    @endauth
    {{-- Progressive enhancement: without these scripts the forms still work,
         they just lose in-browser compression, device signing and the
         submit-on-change of the language picker. --}}
    <script src="{{ asset('js/identity-capture.js') }}" defer></script>
    <script src="{{ asset('js/signing-device.js') }}" defer></script>
    <script src="{{ asset('js/language-switch.js') }}" defer></script>
</head>
<body class="{{ auth()->check() ? 'shell' : '' }}">
    <a class="skip-link" href="#contenu">{{ __('common.skip_to_content') }}</a>

    @auth
        @php
            $utilisateur = auth()->user();
            $nonLues = $utilisateur->unreadNotifications()->count();
            /*
             * Les initiales, calculees sur le nom affiche. Deux au plus : une
             * pastille n'est pas un identifiant, elle sert a reperer d'un
             * coup d'oeil qu'on est bien dans SON espace.
             */
            $initiales = collect(preg_split('/\s+/', trim($utilisateur->name), -1, PREG_SPLIT_NO_EMPTY))
                ->take(2)
                ->map(fn (string $mot): string => mb_strtoupper(mb_substr($mot, 0, 1)))
                ->implode('');
        @endphp

        <div class="shell__layout">
            {{--
                BARRE LATERALE (D-079).

                Elle remplace la barre du haut sur les quatre roles. Le choix
                d'une coquille unique est du client : une moitie de produit en
                barre laterale et l'autre en barre du haut se serait lue comme
                un chantier interrompu.
            --}}
            <aside class="shell__side" data-shell-side aria-label="{{ __('common.main_navigation') }}">
              <div class="shell__side-inner">
                <a class="shell__brand" href="{{ route('dashboard') }}">
                    <svg class="shell__brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
                        <circle cx="16" cy="16" r="15" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                        <path d="M10 22V10h7a4 4 0 0 1 0 8h-7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>
                        <span class="shell__brand-name">{{ __('common.brand') }}</span>
                        {{-- Le role, pas le slogan : dans un espace connecte, ce
                             qui aide est de savoir avec quel compte on y est. --}}
                        <span class="shell__brand-role">{{ $utilisateur->role->label() }}</span>
                    </span>
                </a>

                <nav>
                    <x-shell-nav :user="$utilisateur" />
                </nav>

                <div class="shell__signout">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">
                            <x-icon name="signout" />
                            {{ __('common.sign_out') }}
                        </button>
                    </form>
                </div>
              </div>
            </aside>

            <div class="shell__scrim" data-shell-scrim aria-hidden="true"></div>

            <div class="shell__main">
                <header class="shell__top">
                    <button type="button" class="shell__toggle"
                            data-shell-toggle
                            aria-expanded="false"
                            aria-label="{{ __('common.open_menu') }}"
                            data-label-open="{{ __('common.open_menu') }}"
                            data-label-close="{{ __('common.close_menu') }}">
                        <x-icon name="menu" :size="22" />
                    </button>

                    <span class="shell__crumb">@yield('title', __('common.dashboard'))</span>

                    <div class="shell__top-actions">
                        <a class="shell__icon-link" href="{{ route('notifications.index') }}"
                           aria-label="{{ $nonLues > 0 ? trans_choice('notifications.unread_count', $nonLues) : __('common.notifications') }}">
                            <x-icon name="bell" />
                            @if ($nonLues > 0)
                                <span class="shell__badge" aria-hidden="true">{{ $nonLues > 99 ? '99+' : $nonLues }}</span>
                            @endif
                        </a>

                        <x-language-switcher />

                        <span class="shell__identity">
                            <span class="shell__initials" aria-hidden="true">{{ $initiales }}</span>
                            <span>
                                <span class="shell__identity-name">{{ $utilisateur->name }}</span>
                                <span class="shell__identity-role">{{ $utilisateur->role->label() }}</span>
                            </span>
                        </span>
                    </div>
                </header>

                <main id="contenu" tabindex="-1" class="shell__content">
                    @yield('content')
                </main>

                <footer class="shell__foot">
                    <p class="u-flush">{{ __('common.footer') }}</p>
                </footer>
            </div>
        </div>
    @endauth

    @guest
        {{--
            POUR UN VISITEUR ANONYME, LA BARRE DU HAUT RESTE (D-072).

            Connexion, creation de compte, mot de passe oublie, etat du
            service, pages d'erreur : aucune n'a de menu de travail a porter.
            Une barre laterale y serait une coquille vide.
        --}}
        <header class="site-header">
            <div class="container">
                <span class="brand">
                    <span class="brand__name">{{ __('common.brand') }}</span>
                    <span class="brand__tag">{{ __('common.tagline') }}</span>
                </span>
                <nav class="site-nav" aria-label="{{ __('common.main_navigation') }}">
                    <a href="{{ route('home') }}"
                       @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('common.home') }}</a>
                    <a href="{{ route('login') }}"
                       @if (request()->routeIs('login')) aria-current="page" @endif>{{ __('common.sign_in') }}</a>
                    <a href="{{ route('register') }}"
                       @if (request()->routeIs('register')) aria-current="page" @endif>{{ __('common.create_account') }}</a>
                    <x-language-switcher />
                </nav>
            </div>
        </header>

        <main id="contenu" tabindex="-1">
            <div class="container">
                @yield('content')
            </div>
        </main>

        <footer class="site-footer">
            <div class="container">
                <p class="u-flush">{{ __('common.footer') }}</p>
            </div>
        </footer>
    @endguest
</body>
</html>
