@extends('officer.verification._layout')
@section('etape')
    @php $etape = $etapes->get(4); $charge = $etape?->payload ?? []; $actes = $charge['payload']['records'] ?? []; @endphp

    <x-card :title="__('verification.steps.4')">
        <p class="u-note">{{ __('officer.step4.intro') }}</p>
        @include('officer.verification.result', ['etape' => $etape])

        @if ($etape?->result === \App\Enums\VerificationResult::ProviderUnavailable)
            <x-alert variant="attention" :title="__('officer.step4.unreachable_title')">
                {{ $charge['message'] ?? __('officer.step4.no_answer') }}
                {{ __('officer.step4.unreachable_body') }}
            </x-alert>
        @endif

        <dl class="review">
            <div class="review__row"><dt>{{ __('officer.step4.name_searched') }}</dt><dd>{{ $demande->full_name_at_birth }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step1.date_of_birth') }}</dt><dd>{{ $demande->date_of_birth?->format('d/m/Y') }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step4.place') }}</dt><dd>{{ $demande->place_of_birth }}</dd></div>
            <div class="review__row"><dt>{{ __('officer.step4.year') }}</dt><dd>{{ $demande->registration_year }}</dd></div>
        </dl>

        @if ($charge !== [])
            <p><strong>{{ $charge['message'] ?? '' }}</strong></p>
        @endif

        @if ($actes !== [])
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('officer.step4.records_found') }}">
                <table>
                    <caption class="visually-hidden">{{ __('officer.step4.records_found') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('officer.step4.certificate_number') }}</th>
                        <th scope="col">{{ __('common.name') }}</th>
                        <th scope="col">{{ __('officer.step4.birth') }}</th>
                        <th scope="col">{{ __('officer.step4.record_state') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($actes as $acte)
                            <tr>
                                <td>{{ $acte['certificate_number'] ?? '' }}</td>
                                <td>{{ $acte['full_name'] ?? '' }}</td>
                                <td>{{ $acte['date_of_birth'] ?? '' }}, {{ $acte['place_of_birth'] ?? '' }}</td>
                                <td>
                                    <span class="badge badge--{{ ($acte['record_status'] ?? '') === 'active' ? 'success' : 'attention' }}">
                                        {{ ($acte['record_status'] ?? '') === 'active' ? __('officer.step4.record_active') : __('officer.step4.record_destroyed') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($actes) > 1)
                <x-alert variant="attention" :title="__('officer.step4.several_title')">
                    {{ __('officer.step4.several_body', ['count' => count($actes)]) }}
                </x-alert>
            @endif
        @endif

        @if ($peutDecider)
            <form method="POST" action="{{ route('officer.verification.registry', $demande) }}">
                @csrf
                <x-button type="submit" variant="primary">
                    {{ $etape ? __('officer.step4.run_search_again') : __('officer.step4.run_search') }}
                </x-button>
            </form>
        @endif
    </x-card>
@endsection
