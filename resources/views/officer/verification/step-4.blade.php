@extends('officer.verification._layout')
@section('etape')
    @php $etape = $etapes->get(4); $charge = $etape?->payload ?? []; $actes = $charge['payload']['records'] ?? []; @endphp

    <x-card title="Recherche dans le registre d'état civil">
        <p class="u-note">Recherche de l'acte d'origine à partir des informations déclarées.</p>
        @include('officer.verification.result', ['etape' => $etape])

        @if ($etape?->result === \App\Enums\VerificationResult::ProviderUnavailable)
            <x-alert variant="attention" title="Registre injoignable">
                {{ $charge['message'] ?? "Le registre n'a pas répondu." }}
                Relancez la recherche, ou escaladez le dossier au maire si
                l'indisponibilité se prolonge.
            </x-alert>
        @endif

        <dl class="review">
            <div class="review__row"><dt>Nom recherché</dt><dd>{{ $demande->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>Date de naissance</dt><dd>{{ $demande->date_of_birth?->format('d/m/Y') }}</dd></div>
            <div class="review__row"><dt>Lieu</dt><dd>{{ $demande->place_of_birth }}</dd></div>
            <div class="review__row"><dt>Année</dt><dd>{{ $demande->registration_year }}</dd></div>
        </dl>

        @if ($charge !== [])
            <p><strong>{{ $charge['message'] ?? '' }}</strong></p>
        @endif

        @if ($actes !== [])
            <div class="table-wrap">
                <table>
                    <caption class="visually-hidden">Actes trouvés</caption>
                    <thead><tr><th scope="col">Numéro d'acte</th><th scope="col">Nom</th><th scope="col">Naissance</th><th scope="col">État</th></tr></thead>
                    <tbody>
                        @foreach ($actes as $acte)
                            <tr>
                                <td>{{ $acte['certificate_number'] ?? '—' }}</td>
                                <td>{{ $acte['full_name'] ?? '—' }}</td>
                                <td>{{ $acte['date_of_birth'] ?? '—' }} — {{ $acte['place_of_birth'] ?? '—' }}</td>
                                <td>
                                    <span class="badge badge--{{ ($acte['record_status'] ?? '') === 'active' ? 'success' : 'attention' }}">
                                        {{ ($acte['record_status'] ?? '') === 'active' ? 'Registre actif' : 'Registre détruit' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($actes) > 1)
                <x-alert variant="attention" title="Plusieurs actes correspondent">
                    Le registre renvoie {{ count($actes) }} actes. Il vous revient de
                    les départager, ou d'escalader le dossier au maire.
                </x-alert>
            @endif
        @endif

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.registry', $demande) }}">
                @csrf
                <x-button type="submit" variant="primary">
                    {{ $etape ? 'Relancer la recherche' : 'Lancer la recherche' }}
                </x-button>
            </form>
        @endif
    </x-card>
@endsection
