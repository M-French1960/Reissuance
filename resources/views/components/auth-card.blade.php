@props(['title', 'intro' => null])

<div class="auth-shell">
    <div class="card auth-card">
        <h1>{{ $title }}</h1>
        @if ($intro)
            <p class="auth-card__intro">{{ $intro }}</p>
        @endif

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- Recapitulatif d'erreurs, annonce aux lecteurs d'ecran. Chaque
             message dit ce qui s'est passe et ce qu'il faut faire (8.1). --}}
        @if ($errors->any())
            <div class="alert alert--danger" role="alert" tabindex="-1" id="erreurs">
                {{-- ELLE ETAIT EN DUR, EN FRANCAIS, SUR TOUS LES ECRANS
                     D'AUTHENTIFICATION (D-080). Un lecteur anglophone qui se
                     trompait de mot de passe lisait « Un problème empêche de
                     continuer » au-dessus d'un message anglais. --}}
                <p class="alert__title">{{ trans_choice('auth.errors_blocking', $errors->count(), ['count' => $errors->count()]) }}</p>
                <ul class="alert__list">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
