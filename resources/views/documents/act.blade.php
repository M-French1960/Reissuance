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

    @unless ($valeurJuridique)
        <div class="mention-finale">
            <hr>
            <strong>{{ $mentionDemo }}</strong>
            <p>{{ __('documents.act.final_notice') }}</p>
        </div>
    @endunless
@endsection
