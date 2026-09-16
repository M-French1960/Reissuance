{{--
    Switching language.

    A POST form, not a set of links: this writes to the session and to the
    account, and a link that changes server state would be followed by
    crawlers and link prefetchers. The project also enforces the rule in
    LinkVerbsTest.

    It submits on change, and keeps a visible button for anyone browsing
    without JavaScript. The button hides itself only once the script has
    confirmed it can take over.
--}}
<form method="POST" action="{{ route('locale.update') }}" class="lang-switch" data-lang-switch>
    @csrf
    <label class="visually-hidden" for="locale-choice">{{ __('common.change_language') }}</label>
    <select id="locale-choice" name="locale" class="lang-switch__select">
        @foreach (\App\Support\Locales::SUPPORTED as $code => $nom)
            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $nom }}</option>
        @endforeach
    </select>
    <button type="submit" class="lang-switch__go" data-lang-go>{{ __('common.change_language') }}</button>
</form>
