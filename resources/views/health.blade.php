@extends('layouts.app')
@section('title', __('admin.health.title'))

@section('content')
    <h1>{{ __('admin.health.title') }}</h1>

    <x-card>
        <p>
            <span class="badge badge--{{ $healthy ? 'success' : 'danger' }}">
                {{ $healthy ? __('admin.health.operational') : __('admin.health.degraded') }}
            </span>
        </p>

        {{--
            The detail is not public: it told an anonymous visitor the database
            server version, the application account and the state of the audit
            log privileges (D-058).
        --}}
        @unless ($detaille)
            <p class="u-note">{{ __('admin.health.detail_restricted') }}</p>
        @endunless
    </x-card>

    @if ($detaille)
        <x-card>
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('admin.health.checks_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('admin.health.checks_aria') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('admin.health.check') }}</th>
                        <th scope="col">{{ __('admin.health.state') }}</th>
                        <th scope="col">{{ __('admin.health.detail') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($checks as $check)
                            <tr>
                                <td data-label="{{ __('admin.health.check') }}">{{ $check['label'] }}</td>
                                {{--
                                    Trois etats depuis D-094 : une sonde
                                    consultative signale sans declarer la
                                    panne. Le rouge doit rester rare pour
                                    rester lu.
                                --}}
                                @php
                                    $consultative = ($check['advisory'] ?? false) && ! $check['ok'];
                                    $ton = match (true) {
                                        $check['ok'] => 'success',
                                        $consultative => 'attention',
                                        default => 'danger',
                                    };
                                    $etat = match (true) {
                                        $check['ok'] => __('admin.health.ok'),
                                        $consultative => __('admin.health.advisory'),
                                        default => __('admin.health.failing'),
                                    };
                                @endphp
                                <td data-label="{{ __('admin.health.state') }}">
                                    <span class="badge badge--{{ $ton }}">{{ $etat }}</span>
                                </td>
                                <td data-label="{{ __('admin.health.detail') }}">{{ $check['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
