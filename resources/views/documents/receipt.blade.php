@extends('documents.layout')

@section('titre', __('documents.receipt.heading'))

@section('contenu')
    {{--
        SAME RULE AS THE CERTIFICATE (D-025): while payments go through the
        demonstration adapter, the notice is the FIRST thing in the document.
        Nobody should be able to mistake a demonstration receipt for a real one.
    --}}
    @if ($simule)
        <div class="bandeau">
            <strong>{{ $mentionDemo }}</strong>
        </div>
    @endif

    <div class="entete">
        <h1>{{ __('documents.receipt.heading') }}</h1>
        <div class="sous-titre">{{ __('documents.receipt.subtitle') }}</div>
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
            <p>{{ __('documents.receipt.final_notice') }}</p>
        </div>
    @endif
@endsection
