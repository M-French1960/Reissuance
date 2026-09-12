{{--
    Le corps de l'acte — IDENTIQUE dans l'acte signe et dans le projet.

    Les champs imprimes ici sont exactement ceux que couvre l'empreinte de
    contenu (DocumentBuilder::FINGERPRINTED_FIELDS). En ajouter un sans
    l'ajouter a l'empreinte rendrait ce champ modifiable apres lecture du
    maire ; un test verifie que les deux listes coincident.
--}}
<h2>Titulaire de l'acte</h2>
<table class="champs">
    <tr><th>Nom à la naissance</th><td>{{ $demande->full_name_at_birth }}</td></tr>
    <tr><th>Né(e) le</th><td>{{ $demande->date_of_birth?->translatedFormat('d F Y') ?? '—' }}</td></tr>
    <tr><th>Lieu de naissance</th><td>{{ $demande->place_of_birth }}</td></tr>
    <tr><th>Année d'enregistrement</th><td>{{ $demande->registration_year }}</td></tr>
    @if ($demande->original_certificate_number)
        <tr><th>Numéro d'acte d'origine</th><td>{{ $demande->original_certificate_number }}</td></tr>
    @endif
</table>

<h2>Filiation</h2>
<table class="champs">
    <tr><th>Père</th><td>{{ $demande->father_name }} ({{ $demande->father_nationality }})</td></tr>
    <tr><th>Mère</th><td>{{ $demande->mother_name }} ({{ $demande->mother_nationality }})</td></tr>
    <tr><th>Adresse des parents</th><td>{{ $demande->parents_address }}</td></tr>
</table>

<h2>Délivrance</h2>
<table class="champs">
    <tr><th>Centre d'état civil</th><td>{{ $demande->center?->name ?? '—' }}</td></tr>
    <tr><th>Commune</th><td>{{ $demande->commune?->name ?? '—' }}</td></tr>
    <tr><th>Référence de la demande</th><td>{{ $demande->reference }}</td></tr>
    <tr><th>Exemplaires demandés</th><td>{{ $demande->copies_requested }}</td></tr>
    <tr><th>Délivré le</th><td>{{ $delivreLe }}</td></tr>
</table>
