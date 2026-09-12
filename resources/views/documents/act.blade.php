@extends('documents.layout')

@section('titre', "Extrait d'acte de naissance — {$demande->reference}")

@section('contenu')
    {{--
        LE BANDEAU EST LA PREMIERE CHOSE DU DOCUMENT. C'est ce qui garantit
        qu'il est la premiere ligne non vide du texte extrait, donc impossible
        a manquer et impossible a recadrer. Un test le relit avec pdftotext.
    --}}
    @unless ($valeurJuridique)
        <div class="bandeau">
            <strong>{{ $mentionDemo }}</strong>
            <p>Ce document ne peut etre presente a aucune administration.</p>
        </div>
    @endunless

    <div class="entete">
        <div class="republique">REPUBLIQUE DU CAMEROUN</div>
        <div class="devise">Paix - Travail - Patrie</div>
        <h1>EXTRAIT D'ACTE DE NAISSANCE</h1>
        <div class="sous-titre">Copie rééditée</div>
    </div>
    <hr>

    @include('documents.partials.body')

    <hr>
    <h2>Signé par</h2>
    <table class="champs">
        <tr><th>Autorité signataire</th><td>{{ $signataire }}</td></tr>
        <tr><th>Qualité</th><td>Maire de {{ $demande->commune?->name ?? '—' }}</td></tr>
    </table>

    @unless ($valeurJuridique)
        <div class="mention-finale">
            <hr>
            <strong>{{ $mentionDemo }}</strong>
            <p>
                Ce document a ete produit par un adaptateur de signature de demonstration.
                Il ne resulte d'aucune signature electronique agreee. La valeur legale d'un
                acte d'etat civil signe electroniquement au Cameroun, ainsi que les exigences
                d'agrement du prestataire de signature, restent a confirmer : voir le bloc A
                de docs/COMPLIANCE_OPEN_QUESTIONS.md.
            </p>
        </div>
    @endunless
@endsection
