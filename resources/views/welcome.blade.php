@extends('layouts.app')
@section('title', 'Accueil')

@section('content')
    {{--
        CETTE PAGE S'ADRESSE A UN CITOYEN, PAS A UN DEVELOPPEUR (D-072).

        Elle a longtemps annonce « Socle technique — jalon 1 : l'authentification
        et les parcours metier arrivent aux jalons suivants », et n'offrait que
        deux liens d'exploitation — l'etat du service et la galerie de
        composants. La premiere page du service disait donc au citoyen que le
        service n'existait pas encore, et ne lui donnait aucun moyen d'entrer.

        Ce qu'elle doit faire, dans cet ordre : dire ce qu'on peut y faire,
        dire ce qu'il faut avoir sous la main, et donner le chemin.
    --}}
    <h1>Réédition d'actes d'état civil</h1>
    <p class="lede">
        Demandez une nouvelle copie de votre acte de naissance, s'il a été perdu,
        détruit ou abîmé. La demande se fait en ligne ; l'acte est établi par
        votre centre d'état civil et signé par votre maire.
    </p>

    @unless ($signatureEngage)
        {{--
            DIT DES L'ACCUEIL, et non a la fin du parcours (D-025, §10).

            Tant que le prestataire de signature est l'adaptateur de
            demonstration, l'acte delivre porte « SANS VALEUR JURIDIQUE ».
            Laisser un citoyen aller jusqu'au bout pour recevoir un document
            inutilisable serait le tromper.
        --}}
        <x-alert variant="danger" title="Service de démonstration">
            Les actes produits par cette installation portent la mention
            <strong>« sans valeur juridique »</strong> et ne peuvent être présentés
            à aucune administration. N'engagez pas de démarche réelle sur cette base.
        </x-alert>
    @endunless

    <div class="grid grid--2">
        <x-card title="Faire une demande">
            <p>
                Créez votre compte, puis suivez les quatre étapes de l'assistant.
                Vous pourrez revenir plus tard : chaque étape est enregistrée.
            </p>
            <div class="row-actions">
                <x-button href="{{ route('register') }}" variant="primary">Créer un compte</x-button>
                <x-button href="{{ route('login') }}" variant="secondary">J'ai déjà un compte</x-button>
            </div>
        </x-card>

        <x-card title="Ce qu'il faut avoir sous la main">
            <ul>
                <li>Votre <strong>pièce d'identité</strong>, que vous photographierez.</li>
                <li>Une <strong>photo de vous</strong>, prise avec votre téléphone.</li>
                <li>Les <strong>informations de l'acte</strong> : date et lieu de naissance,
                    année d'enregistrement, noms de vos parents.</li>
            </ul>
            <p class="u-note">
                Les photos sont réduites sur votre téléphone avant l'envoi, pour
                consommer moins de données.
            </p>
        </x-card>
    </div>

    <x-card title="Comment se passe une demande">
        {{-- Les quatre etapes, du point de vue du demandeur : ce qu'il fait,
             puis ce que l'administration fait, puis ce qu'il recoit. --}}
        <ol class="steps-plain">
            <li><strong>Vous déposez votre demande</strong> avec vos pièces.</li>
            <li><strong>Un officier d'état civil la vérifie</strong> et peut vous
                écrire si une pièce manque.</li>
            <li><strong>Le maire signe l'acte</strong>, ou renvoie le dossier à
                l'officier.</li>
            <li><strong>Vous êtes prévenu</strong> et vous téléchargez votre acte
                depuis votre espace.</li>
        </ol>
        <p class="u-note">
            Vous suivez l'avancement à tout moment depuis <em>Mes demandes</em>, et
            vous pouvez annuler tant que l'acte n'est pas signé.
        </p>
    </x-card>

    <x-card title="Vous êtes officier d'état civil, maire ou administrateur ?">
        <p>
            Votre compte est créé par l'administration ; il ne s'ouvre pas depuis
            cette page. Connectez-vous avec l'adresse qui vous a été communiquée.
        </p>
        <div class="row-actions">
            <x-button href="{{ route('login') }}" variant="secondary">Se connecter</x-button>
            <x-button href="{{ route('health') }}" variant="secondary">État du service</x-button>
            @if (Route::has('dev.ui'))
                <x-button href="{{ route('dev.ui') }}" variant="secondary">Galerie de composants</x-button>
            @endif
        </div>
    </x-card>
@endsection
