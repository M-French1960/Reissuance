@props(['status'])

{{--
    Affiche toujours le libelle en francais, jamais la valeur technique.
    Le prototype affichait « escalated » brut (docs/AUDIT_FRONTEND.md 8.3).
    La couleur ne porte aucune information a elle seule : le texte suffit.
--}}
<span {{ $attributes->merge(['class' => 'badge badge--'.$status->tone()]) }}>
    {{ $status->label() }}
</span>
