{{--
    LE MESSAGE QUI SUIT UNE ACTION, DE LA COULEUR DE CE QUI S'EST PASSE.

    Tout passait par une seule cle de session, rendue en VERT partout. Le vert
    est la couleur de « c'est fait ». Or la meme cle portait des refus et des
    echecs :

    - « Terminez cette étape avant de passer à la suivante. » — le citoyen
      venait d'etre renvoye en arriere, et l'ecran le felicitait ;
    - « Réglez les frais pour envoyer votre demande. » — l'envoi n'a pas eu
      lieu ;
    - « Aucune correspondance » — le resultat d'un controle d'identite
      INFRUCTUEUX, c'est-a-dire la trace exacte d'une piece volee, annonce a
      l'officier dans la couleur du succes ;
    - « Service externe indisponible », « Règlement refusé ».

    Le ton accompagne donc le message : `->with('statusTone', 'danger')`. Sans
    ton, le vert reste — la grande majorite des messages sont bien des succes.
--}}
@php
    $message = session('status');
    // Les enumerations du domaine ont leurs propres tons — `progress`,
    // `waiting`, `neutral` — et l'alerte n'en connait que trois. Tout ce qui
    // n'est pas EXPLICITEMENT un succes se replie donc sur l'attention, qui
    // ne felicite ni n'alarme. Un `default => 'success'` aurait repeint en
    // vert « En attente de confirmation ».
    $variante = match (session('statusTone', 'success')) {
        'success' => 'success',
        'danger' => 'danger',
        default => 'attention',
    };
@endphp

{{--
    ET IL PEUT PORTER UNE ACTION (D-082).

    `x-alert` sait depuis toujours afficher un lien — c'est le « aucune
    impasse » du 8.1 — mais le flash ne le lui passait pas. Un message comme
    « Décision enregistrée. Une autre demande attend d'être prise en charge. »
    annoncait donc quelque chose a faire sans donner le moyen de le faire :
    l'officier devait retrouver la demande suivante dans la file a la main.
--}}
@if ($message)
    <x-alert :variant="$variante"
             :action="session('statusAction')"
             :action-label="session('statusActionLabel')">{{ $message }}</x-alert>
@endif
