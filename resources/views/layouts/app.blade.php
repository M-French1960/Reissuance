<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- Le prototype omettait cette balise sur 3 pages sur 6 : les navigateurs
         mobiles les rendaient dans une fenetre virtuelle de ~980 px. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', 'PHOENIX') — PHOENIX</title>
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>

    <header class="site-header">
        <div class="container">
            <span class="brand">
                <span class="brand__name">PHOENIX</span>
                <span class="brand__tag">Réédition d'actes d'état civil</span>
            </span>
            @hasSection('header-actions')
                <div>@yield('header-actions')</div>
            @endif
        </div>
    </header>

    <main id="contenu" tabindex="-1">
        <div class="container">
            @yield('content')
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p style="margin:0">République du Cameroun — service de réédition d'actes d'état civil</p>
        </div>
    </footer>
</body>
</html>
