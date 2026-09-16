@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
    <h1>Notifications</h1>

    <x-flash />
    <p class="u-note">
        Le détail d'une demande — motif d'un refus compris — se lit sur la page
        de la demande. Ces messages ne le reprennent pas.
    </p>

    @if ($nonLues > 0)
        <form method="POST" action="{{ route('notifications.read') }}">
            @csrf
            <x-button type="submit" variant="secondary">
                Marquer les {{ $nonLues }} non {{ $nonLues > 1 ? 'lues' : 'lue' }} comme lues
            </x-button>
        </form>
    @endif

    @forelse ($notifications as $notification)
        @php $d = $notification->data; @endphp
        <x-card :title="$d['title'] ?? 'Notification'">
            @unless ($notification->read_at)
                <p class="u-flush"><span class="badge badge--progress">Non lue</span></p>
            @endunless

            <p>{{ $d['body'] ?? '' }}</p>

            <p class="u-note">
                {{ $notification->created_at->translatedFormat('d/m/Y à H:i') }}
                @isset($d['reference'])
                    · Demande {{ $d['reference'] }}
                @endisset
            </p>

            @isset($d['request_id'])
                @if (auth()->user()->role === \App\Enums\UserRole::Citizen)
                    <x-button :href="route('citizen.requests.show', $d['request_id'])" variant="secondary">
                        Ouvrir la demande
                    </x-button>
                @elseif (auth()->user()->role === \App\Enums\UserRole::Officer)
                    <x-button :href="route('officer.verification.step', [$d['request_id'], 1])" variant="secondary">
                        Ouvrir le dossier
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
        <x-card title="Aucune notification">
            <p class="u-flush">
                @switch (auth()->user()->role)
                    @case (\App\Enums\UserRole::Citizen)
                        Vous serez prévenu ici à chaque étape de vos demandes.
                        @break
                    @case (\App\Enums\UserRole::Officer)
                        Vous serez prévenu ici lorsque le maire vous retournera
                        un dossier. Les demandes à traiter, elles, se suivent
                        depuis la file de traitement.
                        @break
                    @default
                        Rien ne vous a encore été signalé ici.
                @endswitch
            </p>
        </x-card>
    @endforelse

    {{ $notifications->links() }}
@endsection
