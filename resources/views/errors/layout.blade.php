@extends('layouts.app')
@section('title', $titre)

@section('content')
    {{--
        LES PAGES D'ERREUR SONT DES PAGES DU SERVICE (D-073).

        Elles n'existaient pas : Laravel rendait sa page par defaut, sans
        en-tete, sans pied de page et sans un mot de francais — un ecran blanc
        portant « Not Found ». Un citoyen qui se trompe d'adresse, ou qui suit
        un lien peri, se retrouvait sans rien : ni explication, ni chemin de
        retour.

        CE QU'ELLES NE DISENT PAS, et c'est delibere : pourquoi l'acces est
        refuse. Un 404 servi a la place d'un 403 ne doit rien apprendre — ni
        qu'un dossier existe, ni combien. Voir ActDocumentController.
    --}}
    <h1>{{ $titre }}</h1>

    <x-card>
        {{ $slot ?? '' }}
        @yield('explication')

        <div class="row-actions u-stack-top">
            @auth
                <x-button href="{{ route('dashboard') }}" variant="primary">Retour à mon espace</x-button>
            @else
                <x-button href="{{ route('home') }}" variant="primary">Retour à l'accueil</x-button>
                <x-button href="{{ route('login') }}" variant="secondary">Se connecter</x-button>
            @endauth
        </div>
    </x-card>
@endsection
