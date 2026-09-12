@extends('documents.layout')

@section('titre', "Projet d'acte — {$demande->reference}")

@section('contenu')
    {{--
        UN PROJET N'EST PAS UN ACTE (D-064). Deux differences avec l'acte, et
        elles sont structurelles :
          - ce bandeau, en tete, que l'acte final n'a pas ;
          - aucun bloc « Signe par » : un projet n'a pas de signataire. A sa
            place, l'officier qui l'a redige, parce que la responsabilite du
            contenu est desormais la sienne.
    --}}
    <div class="bandeau">
        <strong>{{ $mentionProjet }}</strong>
        <p>Ce document n’est pas un acte. Il attend la décision du maire.</p>
    </div>

    <div class="entete">
        <div class="republique">REPUBLIQUE DU CAMEROUN</div>
        <div class="devise">Paix - Travail - Patrie</div>
        <h1>PROJET D'EXTRAIT D'ACTE DE NAISSANCE</h1>
        <div class="sous-titre">Copie rééditée — projet soumis à la signature du maire</div>
    </div>
    <hr>

    @include('documents.partials.body')

    <hr>
    <h2>Rédigé par</h2>
    <table class="champs">
        <tr><th>Officier d’état civil</th><td>{{ $redacteur }}</td></tr>
        <tr><th>Centre</th><td>{{ $demande->center?->name ?? '—' }}</td></tr>
        <tr><th>Rédigé le</th><td>{{ $delivreLe }}</td></tr>
    </table>

    <div class="mention-finale">
        <hr>
        <strong>{{ $mentionProjet }}</strong>
        <p>
            Aucune signature n'a ete apposee. Ce projet n'a aucune valeur et ne peut etre
            presente a aucune administration. Seule la decision du maire fait naitre l'acte.
        </p>
    </div>
@endsection
