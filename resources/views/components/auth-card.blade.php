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
                <p class="alert__title">
                    {{ $errors->count() === 1 ? 'Un problème empêche de continuer' : $errors->count().' problèmes empêchent de continuer' }}
                </p>
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
