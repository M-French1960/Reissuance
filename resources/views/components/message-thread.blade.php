@props(['demande', 'messages'])

{{-- Fil d'echanges d'un dossier — « Contact Officer ».
     Le meme composant sert le demandeur et les agents : ce qu'ils voient est
     identique, seul le libelle de l'interlocuteur change. --}}
<x-card title="Échanges sur ce dossier">
    @if ($messages->isEmpty())
        <p class="u-note">Aucun message pour l'instant.</p>
    @else
        <ol class="thread">
            @foreach ($messages as $message)
                <li class="thread__item thread__item--{{ $message->isFromCitizen() ? 'citizen' : 'agent' }}">
                    <p class="thread__meta">
                        <strong>{{ $message->isFromCitizen() ? 'Le demandeur' : $message->author_role->label() }}</strong>
                        <span class="u-note">{{ $message->created_at->translatedFormat('d/m/Y à H:i') }}</span>
                    </p>
                    <p class="thread__body">{{ $message->body }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    @can('message', $demande)
        <form method="POST" action="{{ route('requests.messages.store', $demande) }}">
            @csrf

            <div class="field">
                <label class="field__label" for="message-body">
                    Votre message <span aria-hidden="true">*</span>
                    <span class="field__hint">
                        N'y mettez pas de numéro de pièce ni de mot de passe.
                        Pour ajouter une photographie, passez par votre dossier.
                    </span>
                </label>
                <textarea class="field__control" id="message-body" name="body" rows="3"
                          maxlength="2000" required
                          @if ($errors->has('body')) aria-invalid="true" @endif>{{ old('body') }}</textarea>
                @if ($errors->has('body'))
                    <p class="field__error">{{ $errors->first('body') }}</p>
                @endif
            </div>

            <x-button type="submit" variant="secondary">Envoyer</x-button>
        </form>
    @endcan
</x-card>
