@extends('officer.verification._layout')
@section('etape')
    @php $etape = $etapes->get(2); $charge = $etape?->payload ?? []; @endphp

    <x-card title="Vérification de la pièce d'identité">
        <p class="u-note">Contrôle du numéro de pièce auprès de la base de la police.</p>
        @include('officer.verification.result', ['etape' => $etape])

        @if ($etape?->result === \App\Enums\VerificationResult::ProviderUnavailable)
            <x-alert variant="attention" title="Service externe indisponible">
                {{ $charge['message'] ?? "La base de la police n'a pas répondu." }}
                Vous pouvez relancer ce contrôle, ou poursuivre les autres étapes
                et y revenir. Le résultat est enregistré tel quel : la
                vérification n'a pas abouti, et le dossier en garde la trace.
            </x-alert>
        @endif

        @if ($charge !== [])
            <dl class="review">
                <div class="review__row"><dt>Résultat</dt><dd>{{ $charge['message'] ?? '—' }}</dd></div>
                @if (isset($charge['payload']['name_on_document']))
                    <div class="review__row"><dt>Nom porté par la pièce</dt><dd>{{ $charge['payload']['name_on_document'] }}</dd></div>
                @endif
                @if (isset($charge['payload']['document_status']))
                    <div class="review__row"><dt>État de la pièce</dt><dd>{{ $charge['payload']['document_status'] }}</dd></div>
                @endif
                <div class="review__row"><dt>Référence du contrôle</dt><dd>{{ $charge['correlation_id'] ?? '—' }}</dd></div>
                <div class="review__row"><dt>Service interrogé</dt><dd>{{ $charge['provider'] ?? '—' }}</dd></div>
            </dl>
        @endif

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.identity', $demande) }}">
                @csrf
                <x-button type="submit" variant="primary">
                    {{ $etape ? 'Relancer le contrôle' : 'Lancer le contrôle' }}
                </x-button>
            </form>
        @endif
    </x-card>
@endsection
