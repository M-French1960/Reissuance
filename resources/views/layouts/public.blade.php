{{--
    COQUILLE DES PAGES PUBLIQUES (D-088).

    Pour les pages qu'un visiteur SANS COMPTE doit pouvoir ouvrir : la
    vérification d'authenticité d'un acte, et ce qui viendra ensuite.

    Elle ne passe pas par layouts/app.blade.php, qui suppose un compte connecté
    et pose une barre latérale de navigation par rôle. Elle reprend en revanche
    la charte de la page d'accueil — `home.css` — parce que c'est la même
    façade, vue par la même personne.

    La page d'accueil, elle, garde son propre en-tête : sa navigation renvoie à
    ses propres sections, ce qu'une coquille partagée ne peut pas porter.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>@yield('title') | {{ __('common.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    <script src="{{ asset('js/language-switch.js') }}" defer></script>
</head>
<body class="home">
    <a class="skip-link" href="#contenu">{{ __('common.skip_to_content') }}</a>

    <header class="home-header">
        <div class="home-wrap">
            <nav class="home-nav" aria-label="{{ __('common.main_navigation') }}">
                <a class="home-brand" href="{{ route('home') }}">
                    <svg class="home-brand__mark" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
                        <circle cx="16" cy="16" r="15" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                        <path d="M10 22V10h7a4 4 0 0 1 0 8h-7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="home-brand__name">{{ __('common.brand') }}</span>
                </a>

                <div class="home-nav__actions">
                    <x-language-switcher />
                    <a class="home-btn home-btn--ghost" href="{{ route('login') }}">{{ __('common.sign_in') }}</a>
                </div>
            </nav>
        </div>
    </header>

    {{-- `tabindex="-1"` : sans lui, le lien d'évitement déplace la vue mais
         PAS le focus, et la personne qui l'a suivi au clavier repart du haut
         de la page. Un test le vérifie sur chaque coquille. --}}
    <main id="contenu" tabindex="-1" class="public-main home-wrap">
        @yield('content')
    </main>

    <footer class="home-footer">
        <div class="home-wrap">
            <div class="home-footer__bottom">
                <span>{{ __('home.footer_year', ['year' => now()->year]) }}</span>
                <span>{{ __('common.footer') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
