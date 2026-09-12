@extends('documents.layout')

@section('titre', 'Reçu de règlement')

@section('contenu')
    {{--
        MEME REGLE QU'A L'ACTE (D-025) : tant que l'encaissement passe par
        l'adaptateur factice, la mention est la PREMIERE chose du document. Un
        recu de demonstration ne doit pouvoir etre confondu avec une quittance
        par personne.
    --}}
    @if ($simule)
        <div class="bandeau">
            <strong>{{ $mentionDemo }}</strong>
        </div>
    @endif

    <div class="entete">
        <h1>REÇU DE RÈGLEMENT</h1>
        <div class="sous-titre">Réédition d'acte d'état civil</div>
    </div>
    <hr>

    <table class="champs">
        @foreach ($elements as $libelle => $valeur)
            <tr><th>{{ $libelle }}</th><td>{{ $valeur }}</td></tr>
        @endforeach
    </table>

    @if ($simule)
        <div class="mention-finale">
            <hr>
            <strong>{{ $mentionDemo }}</strong>
            <p>
                Ce document a été produit par un adaptateur de démonstration.
                Aucun opérateur de paiement n'a été sollicité et aucune somme
                n'a changé de main. Il ne vaut ni quittance, ni preuve de paiement.
            </p>
        </div>
    @endif
@endsection
