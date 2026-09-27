@props([
    'title',
    'lede' => null,
    'note' => null,
    'level' => 'h1',
])

{{--
    LA BANNIERE DE TETE D'UN ECRAN (D-100).

    Elle existait depuis D-079 sous le nom `.dash-welcome`, et uniquement sur
    les tableaux de bord. La maquette du dossier cible la porte sur TOUS les
    écrans du demandeur ; la recopier quatre fois aurait créé quatre versions
    qui divergeraient à la première retouche. Elle est donc un composant, et
    son nom ne dit plus « tableau de bord ».

    `level` existe parce que le tableau de bord porte déjà un `<h1>` propre et
    met ici un `<h2>` : le niveau de titre suit la structure du document, il ne
    suit pas l'apparence. Une page qui saute du h1 au h3 est illisible au
    lecteur d'écran, et aucune couleur ne le rattrape.
--}}
<section {{ $attributes->merge(['class' => 'page-hero']) }}>
    <span class="page-hero__orb" aria-hidden="true"></span>

    <div class="page-hero__text">
        <{{ $level }} class="page-hero__title">{{ $title }}</{{ $level }}>
        @if ($lede)
            <p>{{ $lede }}</p>
        @endif
        @if ($note)
            <p class="page-hero__note">{{ $note }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="page-hero__actions">{{ $slot }}</div>
    @endif
</section>
