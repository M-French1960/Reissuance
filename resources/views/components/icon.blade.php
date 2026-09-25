@props(['name', 'size' => 20])
{{--
    Pictogrammes de l'interface.

    EN LIGNE, ET PAS DANS UN FICHIER DE POLICE. Une police d'icônes serait un
    quatrième téléchargement bloquant, et rendrait des carrés vides tant
    qu'elle n'est pas arrivée. Un SVG en ligne est là dès le premier octet du
    HTML, il hérite de la couleur du texte, et il grossit avec elle.

    Toujours décoratif : chaque appel est accompagné d'un texte visible. C'est
    pourquoi aria-hidden est posé ici une fois pour toutes, plutôt que laissé
    à l'appelant qui l'oublierait.
--}}
@php
    $traces = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'file' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'queue' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'pen' => '<path d="M15 4l5 5L8 21H3v-5z"/><path d="m13 6 5 5"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.5-5 6.5-5s5.5 1.5 6.5 5"/><path d="M17 5.5a3.5 3.5 0 0 1 0 7M18.5 20c-.3-1.6-.9-2.9-1.7-3.9"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11.5 6.8"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7l1.5-1.5"/>',
        'log' => '<path d="M4 5h16M4 10h16M4 15h10"/><circle cx="18" cy="18" r="3"/>',
        'sliders' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
        'signout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.5 1.7-2 3-2s2.4.5 3 2M14 10h4M14 13h4"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'icon']) }}
     width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">{!! $traces[$name] ?? $traces['info'] !!}</svg>
