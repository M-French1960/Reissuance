@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
    'block' => false,
    'disabled' => false,
])

@php
    $classes = 'btn btn--'.$variant.($block ? ' btn--block' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    {{--
        @disabled est appliqué ICI, sur un <button> HTML.
        Écrit sur la balise de composant — <x-button @disabled(...)> — il
        casse la compilation Blade : le compilateur de composants ne traite
        pas les directives dans la liste d'attributs, et la sortie produite
        est du PHP déséquilibré. Le symptôme est une ParseError sur un
        « endif » situé des dizaines de lignes plus loin, ce qui n'aide pas.
        Passer par une propriété évite le piège.
    --}}
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
