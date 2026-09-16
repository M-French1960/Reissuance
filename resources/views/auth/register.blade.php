@extends('layouts.app')
@section('title', 'Créer un compte')

@section('content')
    <x-auth-card title="Créer un compte citoyen"
                 intro="Ce formulaire crée uniquement un compte citoyen. Les comptes d'officier et de maire sont créés par l'administration.">
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="grid grid--2">
                <x-field name="first_name" label="Prénom" required autocomplete="given-name"
                         :error="$errors->first('first_name')" />
                <x-field name="last_name" label="Nom" required autocomplete="family-name"
                         :error="$errors->first('last_name')" />
            </div>

            <x-field name="email" label="Adresse électronique" type="email" required
                     autocomplete="username" :error="$errors->first('email')" />

            <x-field name="password" label="Mot de passe" type="password" required
                     autocomplete="new-password"
                     hint="Au moins 12 caractères, avec majuscules, minuscules, chiffres et symboles. Une suite de mots sans rapport avec vous est plus sûre qu'un mot compliqué."
                     :error="$errors->first('password')" />

            <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password"
                     required autocomplete="new-password" />

            <div class="field field--inline">
                <input type="checkbox" id="accepts_terms" name="accepts_terms" value="1"
                       class="field__checkbox" required
                       @if ($errors->has('accepts_terms')) aria-invalid="true" aria-describedby="accepts_terms-error" @endif>
                <label for="accepts_terms">
                    J'accepte que mes données soient traitées pour instruire ma demande.
                </label>
            </div>
            @if ($errors->has('accepts_terms'))
                <p class="field__error" id="accepts_terms-error">{{ $errors->first('accepts_terms') }}</p>
            @endif

            {{--
                ON NE FAIT PAS COCHER UNE CASE A L'AVEUGLE (D-075).

                La case etait seule : « J'accepte que mes données soient
                traitées » — sans un mot sur QUELLES donnees, vues par QUI, ni
                pour combien de temps. Le projet sait pourtant que le texte de
                consentement n'est pas redige : c'est la question B5 de
                docs/COMPLIANCE_OPEN_QUESTIONS.md. L'ecran, lui, ne le disait
                pas ; il presentait une phrase comme si elle etait la notice
                complete.

                Ce qui suit n'affirme RIEN de juridique (§10) : ce sont les
                faits verifiables dans le code. Et la derniere ligne dit
                franchement ce qui manque, plutot que de l'inventer.
            --}}
            <details class="field">
                <summary>Ce que le service fait de vos données</summary>
                <ul>
                    <li>Vos informations et vos photographies ne sont vues que par
                        <strong>l'officier du centre d'état civil que vous choisissez</strong>
                        et par <strong>le maire de sa commune</strong>.</li>
                    <li>Votre <strong>numéro de pièce d'identité est chiffré</strong> : il
                        n'est lisible ni dans la base, ni par l'administration du service.
                        Il ne sert qu'au contrôle auprès de la base de la police.</li>
                    <li>Vos <strong>photographies sont stockées hors du site web</strong> et
                        ne sont accessibles par aucune adresse publique.</li>
                    <li>Le <strong>journal d'audit</strong> retient qui a fait quoi et quand.
                        Il ne contient ni photographie, ni numéro de pièce.</li>
                </ul>
                <p class="u-note">
                    <strong>Ce qui n'est pas encore arrêté :</strong> la durée de conservation
                    de vos données et la notice complète relèvent de règles qui restent à
                    confirmer avec l'administration. Elles seront publiées ici.
                </p>
            </details>

            <x-button type="submit" variant="primary" block>Créer mon compte</x-button>
        </form>

        <p class="auth-card__links">
            <a href="{{ route('login') }}">J'ai déjà un compte</a>
        </p>
    </x-auth-card>
@endsection
