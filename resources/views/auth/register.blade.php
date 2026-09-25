@extends('layouts.auth')
@section('title', __('common.create_account'))

@section('aside_title', __('auth.register_aside_title'))
@section('aside_lead', __('auth.register_aside_lead'))

@section('aside_extra')
    {{--
        TROIS ARGUMENTS, TOUS VERIFIABLES DANS LE LOGICIEL.

        La maquette annoncait « Notifications par SMS ». Les canaux de
        RequestStatusChanged sont `database` et `mail` : il n'existe aucun
        envoi de SMS. L'argument devient le courriel, qui lui est vrai.
    --}}
    <ul class="auth__points">
        @foreach ([['online', 'file'], ['notify', 'bell'], ['privacy', 'shield']] as [$cle, $icone])
            <li>
                <span class="auth__points-icon" aria-hidden="true"><x-icon :name="$icone" /></span>
                <span>
                    <strong>{{ __("auth.register_point_{$cle}_title") }}</strong>
                    <span>{{ __("auth.register_point_{$cle}_body") }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endsection

@section('card_title', __('auth.register_title'))
@section('card_intro', __('auth.register_intro'))

@section('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="grid grid--2">
            <x-field name="first_name" :label="__('auth.first_name')" required autocomplete="given-name"
                     :error="$errors->first('first_name')" />
            <x-field name="last_name" :label="__('auth.last_name')" required autocomplete="family-name"
                     :error="$errors->first('last_name')" />
        </div>

        {{--
            NI TELEPHONE NI SEXE, ET C'EST DELIBERE.

            La maquette rendait les deux obligatoires. Le telephone y servait
            a « recevoir vos codes et notifications par SMS » : aucun SMS
            n'est envoye, et le telephone est deja demande au profil, la ou il
            sert. Le sexe n'est utilise nulle part dans l'application — ni
            pour instruire une demande, ni pour rediger un acte. Demander une
            donnee personnelle dont on n'a pas l'usage sur une plateforme
            d'etat civil ne se justifie pas ; si l'administration en a besoin
            pour l'acte, elle le dira et le champ suivra.
        --}}
        <x-field name="email" :label="__('auth.email')" type="email" required
                 autocomplete="username" :error="$errors->first('email')" />

        <div class="field">
            <label class="field__label" for="password">
                {{ __('auth.password') }}
                <span aria-hidden="true">*</span>
                <span class="visually-hidden">({{ __('common.required_field') }})</span>
            </label>

            <div class="auth__password" data-password
                 data-label-show="{{ __('auth.show_password') }}"
                 data-label-hide="{{ __('auth.hide_password') }}">
                <input type="password" id="password" name="password" class="field__control"
                       autocomplete="new-password" required
                       aria-describedby="regles-mot-de-passe"
                       @if ($errors->has('password')) aria-invalid="true" @endif>
            </div>

            <p class="auth__caps" data-caps role="status">{{ __('auth.caps_lock_on') }}</p>

            {{--
                LES REGLES AFFICHEES SONT CELLES QUI SONT APPLIQUEES.

                La maquette annoncait « 8 caractères minimum, une lettre, un
                chiffre ». La politique reelle (App\Rules\PasswordPolicy) exige
                DOUZE caracteres, majuscule, minuscule, chiffre ET symbole.
                Quelqu'un qui aurait suivi la liste de la maquette aurait vu
                les quatre coches vertes, puis son inscription refusee sans
                comprendre.

                La liste ne valide rien : elle previent. Le serveur decide, et
                il applique une regle de plus que le navigateur ne peut pas
                verifier (NotAWeakPassword).
            --}}
            <div id="regles-mot-de-passe">
                <p class="field__hint">{{ __('auth.password_rules_title') }}</p>
                <ul class="auth__rules" data-rules>
                    @foreach (['length', 'mixed', 'number', 'symbol'] as $regle)
                        <li data-rule="{{ $regle }}">
                            <span class="auth__rules-mark" aria-hidden="true"></span>
                            {{ __("auth.password_rule_{$regle}") }}
                        </li>
                    @endforeach
                </ul>
                <p class="u-note">{{ __('auth.password_rule_note') }}</p>
            </div>

            @if ($errors->has('password'))
                <p class="field__error" id="password-error">{{ $errors->first('password') }}</p>
            @endif
        </div>

        <x-field name="password_confirmation" :label="__('auth.confirm_password_field')" type="password"
                 required autocomplete="new-password" />

        <div class="field field--inline">
            <input type="checkbox" id="accepts_terms" name="accepts_terms" value="1"
                   class="field__checkbox" required
                   @if ($errors->has('accepts_terms')) aria-invalid="true" aria-describedby="accepts_terms-error" @endif>
            <label for="accepts_terms">{{ __('auth.accept_terms') }}</label>
        </div>
        @if ($errors->has('accepts_terms'))
            <p class="field__error" id="accepts_terms-error">{{ $errors->first('accepts_terms') }}</p>
        @endif

        {{--
            NOBODY TICKS A BOX BLIND (D-075).

            The box stood alone, "I agree that my data may be processed", with
            not a word on WHICH data, seen by WHOM, or for how long. The
            project already knew the consent text was unwritten: that is
            question B5 of docs/COMPLIANCE_OPEN_QUESTIONS.md. The screen did
            not say so; it presented one sentence as if it were the whole
            notice.

            KEPT AS IT WAS, and the mock-up is the reason to say so: it linked
            to "conditions d'utilisation" and "politique de confidentialite",
            two pages that do not exist. A link to a missing notice is worse
            than the honest summary below, which asserts nothing legal (§10)
            and ends by naming what is still missing.
        --}}
        <details class="field">
            <summary>{{ __('auth.privacy_summary') }}</summary>
            <ul>
                <li>{!! __('auth.privacy_who', [
                    'officer' => '<strong>'.e(__('auth.privacy_who_officer')).'</strong>',
                    'mayor' => '<strong>'.e(__('auth.privacy_who_mayor')).'</strong>',
                ]) !!}</li>
                <li>{!! __('auth.privacy_id', ['strong' => '<strong>'.e(__('auth.privacy_id_strong')).'</strong>']) !!}</li>
                <li>{!! __('auth.privacy_photos', ['strong' => '<strong>'.e(__('auth.privacy_photos_strong')).'</strong>']) !!}</li>
                <li>{!! __('auth.privacy_audit', ['strong' => '<strong>'.e(__('auth.privacy_audit_strong')).'</strong>']) !!}</li>
            </ul>
            <p class="u-note">
                <strong>{{ __('auth.privacy_pending_label') }}</strong>
                {{ __('auth.privacy_pending') }}
            </p>
        </details>

        <x-button type="submit" variant="primary" block>{{ __('auth.create_my_account') }}</x-button>
    </form>

    <p class="auth__links">
        <a href="{{ route('login') }}">{{ __('auth.already_have_account') }}</a>
    </p>
@endsection
