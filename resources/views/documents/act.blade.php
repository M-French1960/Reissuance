@extends('documents.layout')

@section('titre', __('documents.act.title', ['reference' => $demande->reference]))

@section('contenu')
    {{--
        THE BANNER IS THE FIRST THING IN THE DOCUMENT. That is what guarantees
        it is the first non-empty line of the extracted text, so impossible to
        miss and impossible to crop out. A test reads it back with pdftotext.
    --}}
    @unless ($valeurJuridique)
        <div class="bandeau">
            <strong>{{ $mentionDemo }}</strong>
            <p>{{ __('documents.act.banner_body') }}</p>
        </div>
    @endunless

    <div class="entete">
        <div class="republique">{{ __('documents.act.republic') }}</div>
        <div class="devise">{{ __('documents.act.motto') }}</div>
        <h1>{{ __('documents.act.heading') }}</h1>
        <div class="sous-titre">{{ __('documents.act.subtitle') }}</div>
    </div>
    <hr>

    @include('documents.partials.body')

    <hr>
    <h2>{{ __('documents.act.signed_by') }}</h2>
    <table class="champs">
        <tr><th>{{ __('documents.act.signing_authority') }}</th><td>{{ $signataire }}</td></tr>
        <tr><th>{{ __('documents.act.capacity') }}</th><td>{{ __('documents.act.mayor_of', ['commune' => $demande->commune?->name]) }}</td></tr>
    </table>

    {{--
        LE CODE DE VERIFICATION (D-088).

        COMPACT, ET C'EST LA CONTRAINTE PRINCIPALE. La premiere version posait
        un <hr>, un <h2> et deux paragraphes : l'acte passait a DEUX PAGES, et
        `l_acte_tient_sur_une_seule_page` est tombe. D-067 avait deja livre
        cette bataille — un acte d'etat civil accompagne d'une page blanche
        n'est pas un acte qu'une mairie remet a un citoyen. Le bloc tient donc
        en un paragraphe.

        Le code est groupe par quatre parce qu'il sera RETAPE a la main, et
        l'adresse est ecrite en toutes lettres : une administration qui recoit
        un papier n'a pas de lien sur lequel cliquer.

        PAS DE QR CODE : le produire demanderait une bibliotheque de plus, et
        rien ne garantit qu'un guichet dispose d'un lecteur. Le code ecrit
        fonctionne sur tout papier et sur tout telephone.
    --}}
    @if ($codeVerification !== null)
        <p class="verification">
            <strong>{{ __('documents.act.verification_title') }}</strong> :
            {{ __('documents.act.verification_body', ['adresse' => $adresseVerification]) }}
            <span class="empreinte">{{ implode('-', str_split($codeVerification, 4)) }}</span>
        </p>
    @endif

    @unless ($valeurJuridique)
        <div class="mention-finale">
            <hr>
            <strong>{{ $mentionDemo }}</strong>
            <p>{{ __('documents.act.final_notice') }}</p>
        </div>
    @endunless
@endsection
