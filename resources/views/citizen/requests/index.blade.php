@extends('layouts.app')
@section('title', __('citizen.requests_title'))

@section('content')
    <h1>{{ __('citizen.requests_title') }}</h1>

    <x-flash />

    <x-card>
        {{--
            A FORM, NOT A LINK (D-071).

            `citizen.requests.start` CREATES a draft: the route is a POST and
            has to stay one, since a route that creates a resource on a GET
            would fire on a plain browser prefetch. Both buttons here were
            <a href>, so GETs: they returned 404, and the citizen could not
            start an application at all.
        --}}
        @if ($requests->isEmpty())
            <x-empty-state :title="__('citizen.requests_empty_title')">
                <p>{{ __('citizen.requests_empty_body') }}</p>
                <form method="POST" action="{{ route('citizen.requests.start') }}">
                    @csrf
                    <x-button type="submit" variant="primary">{{ __('dashboard.citizen.apply') }}</x-button>
                </form>
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('citizen.requests.start') }}">
                @csrf
                <x-button type="submit" variant="primary">{{ __('dashboard.citizen.apply_again') }}</x-button>
            </form>

            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('citizen.requests_title') }}">
                <table>
                    <caption class="visually-hidden">{{ __('citizen.requests_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">{{ __('common.status') }}</th>
                            <th scope="col">{{ __('common.centre') }}</th>
                            <th scope="col">{{ __('common.submitted_on') }}</th>
                            <th scope="col">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $demande)
                            <tr>
                                <td data-label="{{ __('common.reference') }}">{{ $demande->reference }}</td>
                                <td data-label="{{ __('common.status') }}"><x-status-badge :status="$demande->status" /></td>
                                <td data-label="{{ __('common.centre') }}">{{ $demande->center?->name }}</td>
                                <td data-label="{{ __('common.submitted_on') }}">{{ $demande->submitted_at?->translatedFormat('d/m/Y') }}</td>
                                <td>
                                    @if ($demande->isDraft())
                                        <a href="{{ route('citizen.requests.step', ['reissuanceRequest' => $demande, 'step' => min($demande->last_completed_step + 1, 4)]) }}">{{ __('common.resume') }}</a>
                                    @else
                                        <a href="{{ route('citizen.requests.show', $demande) }}">{{ __('common.track') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links('pagination') }}
        @endif
    </x-card>
@endsection
