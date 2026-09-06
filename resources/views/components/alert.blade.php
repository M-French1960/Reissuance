@props([
    'variant' => 'attention',
    'title' => null,
    'action' => null,
    'actionLabel' => null,
])

{{--
    « Aucune impasse » (8.1) : une alerte dit ce qui s'est passe, et propose
    l'action suivante. Le role alert fait annoncer le message immediatement.
--}}
<div {{ $attributes->merge(['class' => 'alert alert--'.$variant]) }}
     role="{{ $variant === 'danger' ? 'alert' : 'status' }}">
    @if ($title)
        <p class="alert__title">{{ $title }}</p>
    @endif
    {{ $slot }}
    @if ($action && $actionLabel)
        <a class="alert__action" href="{{ $action }}">{{ $actionLabel }}</a>
    @endif
</div>
