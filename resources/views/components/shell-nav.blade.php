@props(['user'])
{{--
    Le menu de l'espace connecté, par rôle.

    IL NE PORTE QUE DES DESTINATIONS QUI EXISTENT. La maquette proposait
    « Paiements », « Paramètres » et « Contacter le support » : trois pages
    qui n'ont jamais été écrites. Un menu de service public qui mène nulle
    part est pire qu'un menu court.

    L'ORDRE N'EST PAS ALPHABÉTIQUE, il suit le travail : ce qu'on vient faire
    d'abord, puis ce qu'on consulte, puis son propre compte.
--}}
@php
    use App\Enums\UserRole;

    $liens = match ($user->role) {
        UserRole::Citizen => [
            ['dashboard', 'dashboard', 'common.dashboard', 'dashboard'],
            ['citizen.requests.index', 'file', 'common.my_requests', 'citizen.requests.*'],
            ['notifications.index', 'bell', 'common.notifications', 'notifications.*'],
            ['citizen.profile.edit', 'user', 'common.my_profile', 'citizen.profile.*'],
            ['two-factor.setup', 'shield', 'common.security', 'two-factor.*'],
        ],
        UserRole::Officer => [
            ['dashboard', 'dashboard', 'common.dashboard', 'dashboard'],
            ['officer.queue', 'queue', 'common.processing_queue', 'officer.queue'],
            ['officer.reports', 'chart', 'officer.reports.title', 'officer.reports'],
            ['notifications.index', 'bell', 'common.notifications', 'notifications.*'],
            ['two-factor.setup', 'shield', 'common.security', 'two-factor.*'],
        ],
        UserRole::Mayor => [
            ['dashboard', 'dashboard', 'common.dashboard', 'dashboard'],
            ['mayor.dashboard', 'pen', 'common.signatures', 'mayor.*'],
            ['notifications.index', 'bell', 'common.notifications', 'notifications.*'],
            ['two-factor.setup', 'shield', 'common.security', 'two-factor.*'],
        ],
        UserRole::Admin => [
            ['dashboard', 'dashboard', 'common.dashboard', 'dashboard'],
            ['admin.users.index', 'users', 'common.accounts', 'admin.users.*'],
            ['admin.assignments.index', 'link', 'common.assignments', 'admin.assignments.*'],
            ['admin.audit.index', 'log', 'common.audit_log', 'admin.audit.*'],
            ['admin.settings.index', 'sliders', 'common.settings', 'admin.settings.*'],
            ['notifications.index', 'bell', 'common.notifications', 'notifications.*'],
            ['two-factor.setup', 'shield', 'common.security', 'two-factor.*'],
        ],
    };

    $nonLues = $user->unreadNotifications()->count();
@endphp
<ul class="shell__nav">
    @foreach ($liens as [$route, $icone, $cle, $motif])
        <li>
            <a href="{{ route($route) }}" @if (request()->routeIs($motif)) aria-current="page" @endif>
                <x-icon :name="$icone" />
                {{ __($cle) }}
                @if ($route === 'notifications.index' && $nonLues > 0)
                    <span class="shell__count">{{ $nonLues > 99 ? '99+' : $nonLues }}</span>
                    <span class="visually-hidden">{{ trans_choice('notifications.unread_count', $nonLues) }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
