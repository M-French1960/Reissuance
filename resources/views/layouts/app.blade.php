<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    {{-- The prototype left this out on 3 pages in 6, so mobile browsers laid
         them out in a ~980px virtual viewport. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>@yield('title', __('common.brand')) | {{ __('common.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    {{-- Progressive enhancement: without these scripts the forms still work,
         they just lose in-browser compression, device signing and the
         submit-on-change of the language picker. --}}
    <script src="{{ asset('js/identity-capture.js') }}" defer></script>
    <script src="{{ asset('js/signing-device.js') }}" defer></script>
    <script src="{{ asset('js/language-switch.js') }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#contenu">{{ __('common.skip_to_content') }}</a>

    <header class="site-header">
        <div class="container">
            <span class="brand">
                <span class="brand__name">{{ __('common.brand') }}</span>
                <span class="brand__tag">{{ __('common.tagline') }}</span>
            </span>
            @auth
                @php
                    $utilisateur = auth()->user();
                    $nonLues = $utilisateur->unreadNotifications()->count();
                @endphp
                <nav class="site-nav" aria-label="{{ __('common.main_navigation') }}">
                    <span class="identity">
                        <strong>{{ $utilisateur->name }}</strong>
                        {{ $utilisateur->role->label() }}
                    </span>
                    <a href="{{ route('dashboard') }}"
                       @if (request()->routeIs('dashboard')) aria-current="page" @endif>{{ __('common.dashboard') }}</a>
                    @if ($utilisateur->role === \App\Enums\UserRole::Admin)
                        <a href="{{ route('admin.users.index') }}"
                           @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>{{ __('common.accounts') }}</a>
                        <a href="{{ route('admin.assignments.index') }}"
                           @if (request()->routeIs('admin.assignments.*')) aria-current="page" @endif>{{ __('common.assignments') }}</a>
                        <a href="{{ route('admin.audit.index') }}"
                           @if (request()->routeIs('admin.audit.*')) aria-current="page" @endif>{{ __('common.audit_log') }}</a>
                        <a href="{{ route('admin.settings.index') }}"
                           @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif>{{ __('common.settings') }}</a>
                    @endif
                    @if ($utilisateur->role === \App\Enums\UserRole::Officer)
                        <a href="{{ route('officer.queue') }}"
                           @if (request()->routeIs('officer.*')) aria-current="page" @endif>{{ __('common.processing_queue') }}</a>
                    @endif
                    @if ($utilisateur->role === \App\Enums\UserRole::Mayor)
                        <a href="{{ route('mayor.dashboard') }}"
                           @if (request()->routeIs('mayor.*')) aria-current="page" @endif>{{ __('common.signatures') }}</a>
                    @endif
                    @if ($utilisateur->role === \App\Enums\UserRole::Citizen)
                        <a href="{{ route('citizen.requests.index') }}"
                           @if (request()->routeIs('citizen.requests.*')) aria-current="page" @endif>{{ __('common.my_requests') }}</a>
                        <a href="{{ route('citizen.profile.edit') }}"
                           @if (request()->routeIs('citizen.profile.*')) aria-current="page" @endif>{{ __('common.my_profile') }}</a>
                    @endif
                    <a href="{{ route('notifications.index') }}"
                       @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
                        {{ __('common.notifications') }}
                        @if ($nonLues > 0)
                            <span class="badge badge--progress">{{ $nonLues }}</span>
                            <span class="visually-hidden">{{ trans_choice('notifications.unread_count', $nonLues) }}</span>
                        @endif
                    </a>
                    <a href="{{ route('two-factor.setup') }}">{{ __('common.security') }}</a>
                    <x-language-switcher />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">{{ __('common.sign_out') }}</x-button>
                    </form>
                </nav>
            @endauth

            {{--
                AND FOR AN ANONYMOUS VISITOR (D-072).

                The header only carried navigation for signed-in people, so
                from any public page, the service status or an error page,
                a visitor had no route to sign in but the address bar.
            --}}
            @guest
                <nav class="site-nav" aria-label="{{ __('common.main_navigation') }}">
                    <a href="{{ route('home') }}"
                       @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('common.home') }}</a>
                    <a href="{{ route('login') }}"
                       @if (request()->routeIs('login')) aria-current="page" @endif>{{ __('common.sign_in') }}</a>
                    {{-- A link like its neighbours: the home page already
                         carries the prominent button, and a uniform bar avoids
                         the white-on-white trap of D-072. --}}
                    <a href="{{ route('register') }}"
                       @if (request()->routeIs('register')) aria-current="page" @endif>{{ __('common.create_account') }}</a>
                    <x-language-switcher />
                </nav>
            @endguest
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
</body>
</html>
