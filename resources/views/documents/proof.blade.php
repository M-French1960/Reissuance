@extends('documents.layout')

@section('titre', __('documents.proof.heading'))

@section('contenu')
    <div class="entete">
        <h1>{{ __('documents.proof.heading') }}</h1>
    </div>
    <hr>

    <h2>{{ __('documents.proof.fingerprint') }}</h2>
    {{-- On a line of its own, in a fixed pitch: a fingerprint gets copied out. --}}
    <p class="empreinte">{{ $empreinte }}</p>

    <h2>{{ __('documents.proof.elements') }}</h2>
    <table class="champs">
        @foreach ($elements as $libelle => $valeur)
            <tr><th>{{ $libelle }}</th><td>{{ $valeur }}</td></tr>
        @endforeach
    </table>

    @if ($sceau !== null)
        <h2>{{ __('documents.proof.seal') }}</h2>
        <p class="empreinte">{{ $sceau }}</p>
    @endif

    @unless ($valeurJuridique)
        <div class="mention-finale">
            <hr>
            <strong>{{ $mentionDemo }}</strong>
        </div>
    @endunless
@endsection
