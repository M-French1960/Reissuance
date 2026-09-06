@extends('layouts.app')
@section('title', 'Double authentification')

@section('content')
    <h1>Double authentification</h1>

    @if (session('status') === 'two-factor-authentication-enabled')
        <x-alert variant="success" title="Secret généré">Scannez le code ci-dessous puis saisissez un code pour confirmer.</x-alert>
    @elseif (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert variant="danger" title="Action impossible">
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </x-alert>
    @endif

    @if ($required && ! $confirmed)
        <x-alert variant="danger" title="Configuration obligatoire">
            Votre rôle exige une double authentification. Tant qu'elle n'est pas
            confirmée, votre compte ne peut pas être activé et vous n'avez accès
            à aucun dossier.
        </x-alert>
    @endif

    <x-card title="État">
        <p>
            @if ($confirmed)
                <span class="badge badge--success">Configurée et confirmée</span>
            @elseif ($enabled)
                <span class="badge badge--waiting">En attente de confirmation</span>
            @else
                <span class="badge badge--neutral">Non configurée</span>
            @endif
        </p>

        @if (! $enabled)
            <p>Vous aurez besoin d'une application d'authentification sur votre téléphone
            (par exemple Google Authenticator, Aegis ou FreeOTP).</p>
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <x-button type="submit" variant="primary">Configurer maintenant</x-button>
            </form>
        @endif
    </x-card>

    @if ($qrCode)
        <x-card title="1. Scannez ce code">
            <div class="qr-wrap">{!! $qrCode !!}</div>
            {{--
                Exception documentee a l'interdiction de {!! !!} (4.5 du brief).
                Le SVG est genere par Fortify a partir du secret du compte : il ne
                contient aucune donnee fournie par un utilisateur, donc aucun
                vecteur d'injection. L'echapper le rendrait simplement illisible.
            --}}
        </x-card>

        <x-card title="2. Confirmez avec un code">
            <form method="POST" action="{{ route('two-factor.confirm') }}">
                @csrf
                <x-field name="code" label="Code à 6 chiffres" required
                         inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                         :error="$errors->first('code')" />
                <x-button type="submit" variant="primary">Confirmer</x-button>
            </form>
        </x-card>
    @endif

    @if ($confirmed)
        <x-card title="Codes de secours">
            <p>Si vous perdez l'accès à votre téléphone, ces codes vous permettent de
            vous connecter. Chacun ne fonctionne qu'une seule fois. Conservez-les
            hors de votre téléphone.</p>

            @if ($recoveryCodes)
                <div class="recovery-codes">
                    @foreach ($recoveryCodes as $code)<span>{{ $code }}</span>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('two-factor.recovery-codes') }}" style="margin-top:var(--space-4)">
                @csrf
                <x-button type="submit" variant="secondary">Générer de nouveaux codes</x-button>
            </form>
        </x-card>
    @endif
@endsection
