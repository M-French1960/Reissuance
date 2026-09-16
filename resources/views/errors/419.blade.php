@extends('errors.layout', ['titre' => 'Votre session a expiré'])

@section('explication')
    <p>
        Vous êtes resté trop longtemps sur la page avant de l'envoyer. Par
        sécurité, les sessions des comptes officiels sont courtes.
    </p>
    <p class="u-note">
        Reconnectez-vous, puis recommencez&nbsp;: ce que vous aviez déjà validé
        est enregistré.
    </p>
@endsection
