@props(['title', 'action' => null, 'actionLabel' => null])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <h3 class="empty-state__title">{{ $title }}</h3>
    <p>{{ $slot }}</p>
    @if ($action && $actionLabel)
        <x-button :href="$action" variant="primary">{{ $actionLabel }}</x-button>
    @endif
</div>
