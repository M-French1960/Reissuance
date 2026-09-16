{{--
    The body of the certificate, IDENTICAL in the signed act and in the draft.

    The fields printed here are exactly those the content fingerprint covers
    (DocumentBuilder::FINGERPRINTED_FIELDS). Adding one here without adding it
    to the fingerprint would make that field editable after the mayor has read
    the draft; a test checks the two lists match.
--}}
<h2>{{ __('documents.body.holder') }}</h2>
<table class="champs">
    <tr><th>{{ __('documents.body.name_at_birth') }}</th><td>{{ $demande->full_name_at_birth }}</td></tr>
    <tr><th>{{ __('documents.body.born_on') }}</th><td>{{ $demande->date_of_birth?->translatedFormat('d F Y') }}</td></tr>
    <tr><th>{{ __('documents.body.place_of_birth') }}</th><td>{{ $demande->place_of_birth }}</td></tr>
    <tr><th>{{ __('documents.body.registration_year') }}</th><td>{{ $demande->registration_year }}</td></tr>
    @if ($demande->original_certificate_number)
        <tr><th>{{ __('documents.body.original_number') }}</th><td>{{ $demande->original_certificate_number }}</td></tr>
    @endif
</table>

<h2>{{ __('documents.body.parentage') }}</h2>
<table class="champs">
    <tr><th>{{ __('documents.body.father') }}</th><td>{{ $demande->father_name }} ({{ $demande->father_nationality }})</td></tr>
    <tr><th>{{ __('documents.body.mother') }}</th><td>{{ $demande->mother_name }} ({{ $demande->mother_nationality }})</td></tr>
    <tr><th>{{ __('documents.body.parents_address') }}</th><td>{{ $demande->parents_address }}</td></tr>
</table>

<h2>{{ __('documents.body.issue') }}</h2>
<table class="champs">
    <tr><th>{{ __('documents.body.centre') }}</th><td>{{ $demande->center?->name }}</td></tr>
    <tr><th>{{ __('documents.body.commune') }}</th><td>{{ $demande->commune?->name }}</td></tr>
    <tr><th>{{ __('documents.body.request_reference') }}</th><td>{{ $demande->reference }}</td></tr>
    <tr><th>{{ __('documents.body.copies_requested') }}</th><td>{{ $demande->copies_requested }}</td></tr>
    <tr><th>{{ __('documents.body.issued_on') }}</th><td>{{ $delivreLe }}</td></tr>
</table>
