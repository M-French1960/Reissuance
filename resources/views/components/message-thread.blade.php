@props(['demande', 'messages'])

{{-- The message thread on a file, the "Contact Officer" use case.
     One component serves the applicant and the agents: what they see is the
     same, only the label of the other party changes. --}}
<x-card :title="__('messages.title')">
    @if ($messages->isEmpty())
        <p class="u-note">{{ __('messages.empty') }}</p>
    @else
        <ol class="thread">
            @foreach ($messages as $message)
                <li class="thread__item thread__item--{{ $message->isFromCitizen() ? 'citizen' : 'agent' }}">
                    <p class="thread__meta">
                        <strong>{{ $message->isFromCitizen() ? __('messages.from_applicant') : $message->author_role->label() }}</strong>
                        <span class="u-note">{{ $message->created_at->translatedFormat('d/m/Y H:i') }}</span>
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
                    {{ __('messages.your_message') }} <span aria-hidden="true">*</span>
                    <span class="field__hint">{{ __('messages.warning') }}</span>
                </label>
                <textarea class="field__control" id="message-body" name="body" rows="3"
                          maxlength="2000" required
                          @if ($errors->has('body')) aria-invalid="true" @endif>{{ old('body') }}</textarea>
                @if ($errors->has('body'))
                    <p class="field__error">{{ $errors->first('body') }}</p>
                @endif
            </div>

            <x-button type="submit" variant="secondary">{{ __('common.send') }}</x-button>
        </form>
    @endcan
</x-card>
