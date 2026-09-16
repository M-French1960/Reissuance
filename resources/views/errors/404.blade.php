@extends('errors.layout', ['titre' => 'Page introuvable'])

@section('explication')
    <p>
        Cette page n'existe pas, ou elle ne vous est pas accessible. Si vous
        avez suivi un lien reçu par courriel, il a pu expirer.
    </p>
    <p class="u-note">
        Vos demandes en cours restent disponibles depuis votre espace.
    </p>
@endsection
