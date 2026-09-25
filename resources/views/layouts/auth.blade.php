<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', __('common.brand')) | {{ __('common.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    {{-- Amelioration progressive : sans ces fichiers le formulaire s'envoie
         quand meme, il perd seulement l'affichage du mot de passe, l'alerte
         de verrouillage majuscules et la liste vivante des regles. --}}
    <script src="{{ asset('js/auth-form.js') }}" defer></script>
    <script src="{{ asset('js/language-switch.js') }}" defer></script>
</head>
<body class="auth">
    <a class="skip-link" href="#contenu">{{ __('common.skip_to_content') }}</a>

    <div class="auth__split">
        {{--
            LE PANNEAU DE GAUCHE EST DECORATIF, ET IL LE RESTE.

            Il porte le nom du service et une phrase d'accueil. Rien de ce
            qu'il contient n'est necessaire pour se connecter : sur telephone
            il se reduit a un bandeau, et le formulaire passe en premier.
        --}}
        <aside class="auth__aside">
            <span class="auth__orb auth__orb--1" aria-hidden="true"></span>
            <span class="auth__orb auth__orb--2" aria-hidden="true"></span>

            <a class="auth__brand" href="{{ route('home') }}">
                <svg class="auth__brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
                    <circle cx="16" cy="16" r="15" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                    <path d="M10 22V10h7a4 4 0 0 1 0 8h-7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="auth__brand-name">{{ __('common.brand') }}</span>
            </a>

            <div class="auth__aside-body">
                <p class="auth__aside-title">@yield('aside_title')</p>
                <p class="auth__aside-lead">@yield('aside_lead')</p>
                @yield('aside_extra')
            </div>

            <p class="auth__aside-foot">
                {{-- Vers l'accueil, ou les questions frequentes sont reellement
                     repondues : le service n'a pas de page d'aide separee. --}}
                <a href="{{ route('home') }}#questions">{{ __('auth.aside_help') }}</a>
            </p>
        </aside>

        <main id="contenu" tabindex="-1" class="auth__content">
            <div class="auth__card">
                <div class="auth__card-top">
                    <div>
                        <h1>@yield('card_title')</h1>
                        @hasSection('card_intro')
                            <p class="auth__intro">@yield('card_intro')</p>
                        @endif
                    </div>
                    <x-language-switcher />
                </div>

                @if (session('status'))
                    <x-alert variant="success">{{ session('status') }}</x-alert>
                @endif

                {{-- Recapitulatif d'erreurs, annonce aux lecteurs d'ecran. --}}
                @if ($errors->any())
                    <div class="alert alert--danger" role="alert" tabindex="-1" id="erreurs">
                        <p class="alert__title">{{ trans_choice('auth.errors_blocking', $errors->count(), ['count' => $errors->count()]) }}</p>
                        <ul class="alert__list">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
