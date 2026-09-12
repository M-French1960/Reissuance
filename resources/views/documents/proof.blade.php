@extends('documents.layout')

@section('titre', 'Preuve de signature')

@section('contenu')
    <div class="entete">
        <h1>PREUVE DE SIGNATURE</h1>
    </div>
    <hr>

    <h2>Empreinte du document (SHA-256)</h2>
    {{-- Sur une ligne a elle, en chasse fixe : une empreinte se recopie. --}}
    <p class="empreinte">{{ $empreinte }}</p>

    <h2>Éléments de la signature</h2>
    <table class="champs">
        @foreach ($elements as $libelle => $valeur)
            <tr><th>{{ $libelle }}</th><td>{{ $valeur }}</td></tr>
        @endforeach
    </table>

    @if ($sceau !== null)
        <h2>Sceau</h2>
        <p class="empreinte">{{ $sceau }}</p>
    @endif

    @unless ($valeurJuridique)
        <div class="mention-finale">
            <hr>
            <strong>{{ $mentionDemo }}</strong>
        </div>
    @endunless
@endsection
