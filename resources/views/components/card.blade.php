@props(['title' => null])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title)
        <h3 class="card__title">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
