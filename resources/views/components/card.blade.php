@props(['title' => null, 'level' => 2])

{{--
    LE TITRE D'UNE CARTE EST UN <h2>, PAS UN <h3> (D-081).

    Il etait fige a <h3>. Comme une carte se pose directement sous le <h1> de
    la page, chaque ecran du service sautait le niveau 2 : h1, puis h3. C'est
    un echec du critere WCAG 1.3.1 — la structure annoncee ne correspond pas a
    la structure reelle.

    Ce n'est pas cosmetique. Un lecteur d'ecran propose de naviguer de titre en
    titre, et c'est la maniere normale de parcourir une page quand on ne la
    voit pas. Un niveau manquant fait croire qu'une section a ete sautee, ou
    qu'il existe une section parente qu'on n'a pas entendue. Sur un service
    d'etat civil, ou l'on cherche « Ma demande » ou « Ma decision » dans une
    page qui en contient huit, c'est la table des matieres qui ment.

    POURQUOI UN NIVEAU PARAMETRABLE plutot qu'un <h2> fige. Aucune carte n'est
    aujourd'hui imbriquee dans une section qui a deja son <h2> — verifie sur
    les trente vues qui en posent une. Mais la premiere carte posee dans une
    telle section aurait besoin d'un <h3>, et le seul moyen de l'exprimer sans
    rouvrir ce composant est de le laisser passer son niveau. Le defaut est 2,
    parce que c'est le cas de toutes les vues existantes.
--}}
@php
    // Deux garde-fous : un niveau hors de 2..6 n'a pas de sens sous le <h1>
    // d'une page, et une valeur venue d'une variable ne doit jamais pouvoir
    // fabriquer autre chose qu'une balise de titre.
    $niveau = min(6, max(2, (int) $level));
    $balise = 'h'.$niveau;
@endphp

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title)
        <{{ $balise }} class="card__title">{{ $title }}</{{ $balise }}>
    @endif
    {{ $slot }}
</div>
