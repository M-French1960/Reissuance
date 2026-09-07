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
    {{-- Amelioration progressive : sans ce script, les formulaires
         fonctionnent toujours, mais sans compression cote navigateur. --}}
    <script src="{{ asset('js/identity-capture.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>

    <header class="site-header">
        <div class="container">
            <span class="brand">
                <span class="brand__name">PHOENIX</span>
                <span class="brand__tag">Réédition d'actes d'état civil</span>
            </span>
            @auth
                <nav class="site-nav" aria-label="Navigation principale">
                    <span class="identity">
                        <strong>{{ auth()->user()->name }}</strong>
                        {{ auth()->user()->role->label() }}
                    </span>
                    <a href="{{ route('dashboard') }}"
                       @if (request()->routeIs('dashboard')) aria-current="page" @endif>Tableau de bord</a>
                    @if (auth()->user()->role === \App\Enums\UserRole::Admin)
                        <a href="{{ route('admin.users.index') }}"
                           @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>Comptes</a>
                        <a href="{{ route('admin.audit.index') }}"
                           @if (request()->routeIs('admin.audit.*')) aria-current="page" @endif>Journal</a>
                    @endif
                    @if (auth()->user()->role === \App\Enums\UserRole::Officer)
                        <a href="{{ route('officer.queue') }}"
                           @if (request()->routeIs('officer.*')) aria-current="page" @endif>File de traitement</a>
                    @endif
                    @if (auth()->user()->role === \App\Enums\UserRole::Mayor)
                        <a href="{{ route('mayor.dashboard') }}"
                           @if (request()->routeIs('mayor.*')) aria-current="page" @endif>Signatures</a>
                    @endif
                    @if (auth()->user()->role === \App\Enums\UserRole::Citizen)
                        <a href="{{ route('citizen.requests.index') }}"
                           @if (request()->routeIs('citizen.requests.*')) aria-current="page" @endif>Mes demandes</a>
                        <a href="{{ route('citizen.profile.edit') }}"
                           @if (request()->routeIs('citizen.profile.*')) aria-current="page" @endif>Mon profil</a>
                    @endif
                    @php $nonLues = auth()->user()->unreadNotifications()->count(); @endphp
                    <a href="{{ route('notifications.index') }}"
                       @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
                        Notifications
                        @if ($nonLues > 0)
                            <span class="badge badge--progress">{{ $nonLues }}</span>
                            <span class="visually-hidden">{{ $nonLues > 1 ? 'non lues' : 'non lue' }}</span>
                        @endif
                    </a>
                    <a href="{{ route('two-factor.setup') }}">Sécurité</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">Se déconnecter</x-button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main id="contenu" tabindex="-1">
        <div class="container">
            @yield('content')
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p class="u-flush">République du Cameroun — service de réédition d'actes d'état civil</p>
        </div>
    </footer>
</body>
</html>
