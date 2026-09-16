@extends('layouts.app')
@section('title', __('officer.queue.title'))

@section('content')
    <h1>{{ __('officer.queue.title') }}</h1>
    <p>{{ __('officer.queue.centre_line', ['centre' => auth()->user()->center?->name ?? __('common.none')]) }}</p>

    <x-flash />
    @if ($errors->any())
        <x-alert variant="danger" :title="__('officer.action_impossible')">
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </x-alert>
    @endif

    <div class="grid grid--4">
        @foreach ([\App\Enums\RequestStatus::Pending, \App\Enums\RequestStatus::UnderReview, \App\Enums\RequestStatus::AwaitingSignature, \App\Enums\RequestStatus::Escalated] as $s)
            <x-card>
                <p class="stat__label">{{ $s->label() }}</p>
                <p class="stat__value">{{ $counts[$s->value] }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card>
        <form method="GET" action="{{ route('officer.queue') }}" class="toolbar">
            <x-field name="recherche" :label="__('officer.queue.search')" :value="request('recherche')"
                     :hint="__('officer.queue.search_hint')" />
            <div class="field">
                <label class="field__label" for="statut">{{ __('officer.queue.status') }}</label>
                <select class="field__control" id="statut" name="statut">
                    <option value="">{{ __('officer.queue.all') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(request('statut') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="assignation">{{ __('officer.queue.assignment') }}</label>
                <select class="field__control" id="assignation" name="assignation">
                    <option value="">{{ __('officer.queue.assignment_all') }}</option>
                    <option value="moi" @selected(request('assignation') === 'moi')>{{ __('officer.queue.mine') }}</option>
                    <option value="libre" @selected(request('assignation') === 'libre')>{{ __('officer.queue.free') }}</option>
                </select>
            </div>
            <x-field name="depuis" :label="__('officer.queue.submitted_since')" type="date" :value="request('depuis')" />
            <x-button type="submit" variant="secondary">{{ __('common.filter') }}</x-button>
        </form>
    </x-card>

    <x-card>
        @if ($requests->isEmpty())
            <x-empty-state :title="__('officer.queue.no_match_title')">
                {{ __('officer.queue.no_match_body') }}
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('officer.queue.centre_requests') }}">
                <table>
                    <caption class="visually-hidden">{{ __('officer.queue.centre_requests') }}</caption>
                    <thead>
                        <tr>
                            @foreach (['reference' => __('common.reference'), 'status' => __('common.status'), 'submitted_at' => __('common.submitted_on')] as $col => $libelle)
                                <th scope="col" @if ($sort === $col) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    <a href="{{ route('officer.queue', array_merge(request()->query(), ['tri' => $col, 'sens' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc'])) }}">
                                        {{ $libelle }}
                                        @if ($sort === $col)<span aria-hidden="true">{{ $direction === 'asc' ? '&uarr;' : '&darr;' }}</span>@endif
                                    </a>
                                </th>
                            @endforeach
                            <th scope="col">{{ __('common.applicant') }}</th>
                            <th scope="col">{{ __('officer.queue.assigned_to') }}</th>
                            <th scope="col">{{ __('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td><x-status-badge :status="$demande->status" /></td>
                                <td>{{ $demande->submitted_at?->translatedFormat('d/m/Y H:i') }}</td>
                                <td>{{ $demande->full_name_at_birth }}</td>
                                <td>
                                    @if ($demande->assignedOfficer)
                                        {{ $demande->assigned_officer_id === auth()->id() ? __('officer.queue.assigned_to_you') : $demande->assignedOfficer->name }}
                                    @else
                                        {{--
                                            "Unassigned", not "Nobody": the column next door
                                            carries the applicant's NAME, and "Nobody" reads
                                            there like a proper noun rather than "no agent".
                                            Found by looking at the queue.
                                        --}}
                                        <span class="u-note">{{ __('officer.queue.unassigned') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @can('claim', $demande)
                                        <form method="POST" action="{{ route('officer.verification.claim', $demande) }}">
                                            @csrf
                                            <x-button type="submit" variant="primary">{{ __('officer.queue.take_on') }}</x-button>
                                        </form>
                                    @elsecan('view', $demande)
                                        <a href="{{ route('officer.verification.step', ['reissuanceRequest' => $demande, 'step' => 1]) }}">{{ __('common.open') }}</a>
                                    @endcan
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
