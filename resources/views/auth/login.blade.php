@extends('layouts.auth')
@section('title', __('auth.login_title'))

@section('aside_title', __('auth.login_aside_title'))
@section('aside_lead', __('auth.login_aside_lead'))

@section('aside_extra')
    {{--
        FRISE D'EXEMPLE, ET SON INTITULE LE DIT.

        La maquette l'appelait « Exemple de suivi » et montrait deux etapes
        cochees : quelqu'un pouvait croire qu'il s'agissait de SA demande,
        avant meme d'etre connecte. L'intitule dit maintenant qu'elle montre a
        quoi ressemble le parcours, pas ou en est un dossier.
    --}}
    <p class="auth__demo-label">{{ __('auth.login_demo_label') }}</p>
    <ol class="auth__demo">
        @foreach ([1, 2, 3, 4] as $etape)
            <li @class(['is-done' => $etape <= 2])>
                <span class="auth__demo-dot" aria-hidden="true">{{ $etape <= 2 ? '✓' : $etape }}</span>
                <span>{{ __("auth.login_demo_{$etape}") }}</span>
            </li>
        @endforeach
    </ol>
@endsection

@section('card_title', __('auth.login_title'))
@section('card_intro', __('auth.login_intro'))

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{--
            L'IDENTIFIANT EST UNE ADRESSE, PAS UN TELEPHONE.

            La maquette proposait « Téléphone ou adresse e-mail ». Fortify est
            configure sur `email` (config/fortify.php), et rien ne resout un
            numero vers un compte. Offrir le telephone ferait echouer la
            connexion de quiconque le saisirait, sans lui dire pourquoi.
        --}}
        <x-field name="email" :label="__('auth.email')" type="email"
                 autocomplete="username" required autofocus
                 :error="$errors->first('email')" />

        <div class="field">
            <div class="auth__label-row">
                <label class="field__label" for="password">
                    {{ __('auth.password') }}
                    <span aria-hidden="true">*</span>
                    <span class="visually-hidden">({{ __('common.required_field') }})</span>
                </label>
                <a href="{{ route('password.request') }}">{{ __('auth.forgot') }}</a>
            </div>

            <div class="auth__password" data-password
                 data-label-show="{{ __('auth.show_password') }}"
                 data-label-hide="{{ __('auth.hide_password') }}">
                <input type="password" id="password" name="password" class="field__control"
                       autocomplete="current-password" required
                       @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
            </div>

            {{-- Le verrouillage des majuscules explique une bonne part des
                 echecs de connexion, et le champ masque le cache. --}}
            <p class="auth__caps" data-caps role="status">{{ __('auth.caps_lock_on') }}</p>

            @if ($errors->has('password'))
                <p class="field__error" id="password-error">{{ $errors->first('password') }}</p>
            @endif
        </div>

        <div class="field field--inline">
            <input type="checkbox" id="remember" name="remember" class="field__checkbox">
            <label for="remember">{{ __('auth.remember') }}</label>
        </div>

        <x-button type="submit" variant="primary" block>{{ __('common.sign_in') }}</x-button>
    </form>

    {{--
        DEUX LIENS RETIRES DE LA MAQUETTE.

        « Suivre une demande avec un numéro de suivi » : il n'existe aucune
        route publique de suivi, et il ne doit pas en exister — une reference
        est devinable, et le suivi dirait a qui la saisit l'etat du dossier
        d'un inconnu.

        « Espace agent » : il n'y a qu'une seule porte d'entree. Un officier,
        un maire et un administrateur se connectent ici, et c'est leur role
        qui decide de ce qu'ils voient ensuite.
    --}}
    <p class="auth__links">
        <a href="{{ route('register') }}">{{ __('auth.register_link') }}</a>
    </p>

    <p class="u-note">{{ __('auth.staff_note') }}</p>
@endsection
