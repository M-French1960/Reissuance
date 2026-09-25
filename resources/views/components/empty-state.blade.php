@props(['title', 'action' => null, 'actionLabel' => null, 'level' => 2])

{{--
    LE TITRE D'UN ETAT VIDE SUIT SON CONTEXTE (D-081).

    Il etait fige a <h3>, comme celui de `x-card`. Les deux ensemble faisaient
    sauter le niveau 2 sur la moitie des ecrans du service.

    POURQUOI UN NIVEAU PARAMETRABLE, ICI PLUS QU'AILLEURS. Un etat vide est
    toujours DANS quelque chose, mais ce quelque chose n'a pas toujours de
    titre : sur neuf emplacements, six sont poses dans une carte SANS titre —
    l'etat vide est alors le titre de la section, donc un <h2> — et trois dans
    une carte ou une section qui porte deja son <h2>, ou il devient un <h3>.

    Le defaut est 2, et les trois emplacements imbriques passent 3. C'est la
    vue qui connait sa structure ; le composant ne peut pas la deviner.
--}}
@php
    $niveau = min(6, max(2, (int) $level));
    $balise = 'h'.$niveau;
@endphp

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <{{ $balise }} class="empty-state__title">{{ $title }}</{{ $balise }}>
    <p>{{ $slot }}</p>
    @if ($action && $actionLabel)
        <x-button :href="$action" variant="primary">{{ $actionLabel }}</x-button>
    @endif
</div>
