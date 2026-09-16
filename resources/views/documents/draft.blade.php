@extends('documents.layout')

@section('titre', __('documents.draft.title', ['reference' => $demande->reference]))

@section('contenu')
    {{--
        A DRAFT IS NOT A CERTIFICATE (D-064). Two differences from the act, and
        both are structural:
          - this banner, at the top, which the final act does not carry;
          - no "Signed by" block: a draft has no signatory. In its place, the
            officer who wrote it, because responsibility for the content is
            now theirs.
    --}}
    <div class="bandeau">
        <strong>{{ $mentionProjet }}</strong>
        <p>{{ __('documents.draft.banner_awaiting') }}</p>
    </div>

    <div class="entete">
        <div class="republique">{{ __('documents.act.republic') }}</div>
        <div class="devise">{{ __('documents.act.motto') }}</div>
        <h1>{{ __('documents.draft.draft_heading') }}</h1>
        <div class="sous-titre">{{ __('documents.draft.draft_subtitle') }}</div>
    </div>
    <hr>

    @include('documents.partials.body')

    <hr>
    <h2>{{ __('documents.draft.written_by') }}</h2>
    <table class="champs">
        <tr><th>{{ __('documents.draft.officer') }}</th><td>{{ $redacteur }}</td></tr>
        <tr><th>{{ __('documents.draft.centre') }}</th><td>{{ $demande->center?->name }}</td></tr>
        <tr><th>{{ __('documents.draft.written_on') }}</th><td>{{ $delivreLe }}</td></tr>
    </table>

    <div class="mention-finale">
        <hr>
        <strong>{{ $mentionProjet }}</strong>
        <p>{{ __('documents.draft.final_notice') }}</p>
    </div>
@endsection
