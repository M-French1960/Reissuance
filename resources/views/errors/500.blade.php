@extends('errors.layout', ['titre' => 'Le service rencontre une difficulté'])

@section('explication')
    <p>
        Une erreur nous empêche d'afficher cette page. Elle a été enregistrée&nbsp;;
        aucune donnée que vous aviez validée n'est perdue.
    </p>
    <p class="u-note">
        Réessayez dans un moment. Si cela se reproduit, signalez-le à
        l'administration de votre commune.
    </p>
@endsection
