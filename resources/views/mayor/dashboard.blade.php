@extends('layouts.app')
@section('title', __('mayor.queue.title'))

@section('content')
    <h1>{{ __('mayor.queue.title') }}</h1>
    <p>{{ __('mayor.queue.commune_line', ['commune' => auth()->user()->commune?->name ?? __('common.none')]) }}</p>

    <x-flash />

    <x-alert variant="attention" :title="__('mayor.queue.demo_title')">
        {!! __('mayor.queue.demo_body', ['strong' => '<strong>'.e(__('mayor.queue.demo_strong')).'</strong>']) !!}
    </x-alert>

    <x-card :title="__('mayor.queue.ready_title', ['count' => $aSigner->total()])">
        @if ($aSigner->isEmpty())
            <x-empty-state :title="__('mayor.queue.ready_empty_title')">
                {{ __('mayor.queue.ready_empty_body') }}
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('mayor.queue.ready_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('mayor.queue.ready_aria') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('common.reference') }}</th>
                        <th scope="col">{{ __('common.applicant') }}</th>
                        <th scope="col">{{ __('common.centre') }}</th>
                        <th scope="col">{{ __('common.submitted_on') }}</th>
                        <th scope="col">{{ __('common.action') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($aSigner as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td>{{ $demande->full_name_at_birth }}</td>
                                <td>{{ $demande->center?->name }}</td>
                                <td>{{ $demande->submitted_at?->translatedFormat('d/m/Y') }}</td>
                                <td><a href="{{ route('mayor.review', $demande) }}">{{ __('mayor.queue.examine') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $aSigner->links('pagination') }}
        @endif
    </x-card>

    <x-card :title="__('mayor.queue.referred_title', ['count' => $escaladees->total()])">
        <p class="u-note">{{ __('mayor.queue.referred_note') }}</p>

        @if ($escaladees->isEmpty())
            <x-empty-state :title="__('mayor.queue.referred_empty_title')">
                {{ __('mayor.queue.referred_empty_body') }}
            </x-empty-state>
        @else
            <div class="table-wrap" tabindex="0" role="group" aria-label="{{ __('mayor.queue.referred_aria') }}">
                <table>
                    <caption class="visually-hidden">{{ __('mayor.queue.referred_aria') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('common.reference') }}</th>
                        <th scope="col">{{ __('common.applicant') }}</th>
                        <th scope="col">{{ __('mayor.queue.referral_reason') }}</th>
                        <th scope="col">{{ __('common.action') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($escaladees as $demande)
                            <tr>
                                <td>{{ $demande->reference }}</td>
                                <td>{{ $demande->full_name_at_birth }}</td>
                                <td>{{ $demande->decisions->first()?->reason }}</td>
                                <td><a href="{{ route('mayor.review', $demande) }}">{{ __('mayor.queue.rule') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $escaladees->links('pagination') }}
        @endif
    </x-card>
@endsection
