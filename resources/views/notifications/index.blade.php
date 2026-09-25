@extends('layouts.app')
@section('title', __('notifications.title'))

@section('content')
    <h1>{{ __('notifications.title') }}</h1>

    <x-flash />
    <p class="u-note">{{ __('notifications.detail_note') }}</p>

    @if ($nonLues > 0)
        <form method="POST" action="{{ route('notifications.read') }}">
            @csrf
            <x-button type="submit" variant="secondary">
                {{ trans_choice('notifications.mark_all_read', $nonLues, ['count' => $nonLues]) }}
            </x-button>
        </form>
    @endif

    @forelse ($notifications as $notification)
        @php $d = $notification->data; @endphp
        <x-card :title="$d['title'] ?? __('notifications.title')">
            @unless ($notification->read_at)
                <p class="u-flush"><span class="badge badge--progress">{{ __('common.unread') }}</span></p>
            @endunless

            <p>{{ $d['body'] ?? '' }}</p>

            <p class="u-note">
                {{ $notification->created_at->translatedFormat('d/m/Y H:i') }}
                @isset($d['reference'])
                    {{ __('payment.request_line', ['reference' => $d['reference']]) }}
                @endisset
            </p>

            @isset($d['request_id'])
                @if (auth()->user()->role === \App\Enums\UserRole::Citizen)
                    <x-button :href="route('citizen.requests.show', $d['request_id'])" variant="secondary">
                        {{ __('common.open_request') }}
                    </x-button>
                @elseif (auth()->user()->role === \App\Enums\UserRole::Officer)
                    <x-button :href="route('officer.verification.step', [$d['request_id'], 1])" variant="secondary">
                        {{ __('common.open_file') }}
                    </x-button>
                @endif
            @endisset
        </x-card>
    @empty
        {{-- L'ETAT VIDE PARLAIT AU CITOYEN, A TOUT LE MONDE (D-074).

             « Vous serez prévenu ici à chaque étape de VOS DEMANDES » etait
             servi a l'officier, au maire et a l'administrateur — qui n'ont pas
             de demandes. Il leur promettait en outre des messages qu'ils ne
             recevront pas : seul le citoyen est notifie a chaque etape, et
             l'officier uniquement quand le maire lui RETOURNE un dossier. --}}
        <x-card :title="__('notifications.none_title')">
            <p class="u-flush">
                @switch (auth()->user()->role)
                    @case (\App\Enums\UserRole::Citizen)
                        {{ __('notifications.none_citizen') }}
                        @break
                    @case (\App\Enums\UserRole::Officer)
                        {{ __('notifications.none_officer') }}
                        @break
                    @default
                        {{ __('notifications.none_other') }}
                @endswitch
            </p>
        </x-card>
    @endforelse

    {{ $notifications->links() }}
@endsection
