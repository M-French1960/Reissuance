@extends('layouts.app')
@section('title', __('common.create_account'))

@section('content')
    <x-auth-card :title="__('auth.register_title')" :intro="__('auth.register_intro')">
        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="grid grid--2">
                <x-field name="first_name" :label="__('auth.first_name')" required autocomplete="given-name"
                         :error="$errors->first('first_name')" />
                <x-field name="last_name" :label="__('auth.last_name')" required autocomplete="family-name"
                         :error="$errors->first('last_name')" />
            </div>

            <x-field name="email" :label="__('auth.email')" type="email" required
                     autocomplete="username" :error="$errors->first('email')" />

            <x-field name="password" :label="__('auth.password')" type="password" required
                     autocomplete="new-password"
                     :hint="__('auth.password_hint_long')"
                     :error="$errors->first('password')" />

            <x-field name="password_confirmation" :label="__('auth.confirm_password_field')" type="password"
                     required autocomplete="new-password" />

            <div class="field field--inline">
                <input type="checkbox" id="accepts_terms" name="accepts_terms" value="1"
                       class="field__checkbox" required
                       @if ($errors->has('accepts_terms')) aria-invalid="true" aria-describedby="accepts_terms-error" @endif>
                <label for="accepts_terms">{{ __('auth.accept_terms') }}</label>
            </div>
            @if ($errors->has('accepts_terms'))
                <p class="field__error" id="accepts_terms-error">{{ $errors->first('accepts_terms') }}</p>
            @endif

            {{--
                NOBODY TICKS A BOX BLIND (D-075).

                The box stood alone, "I agree that my data may be processed",
                with not a word on WHICH data, seen by WHOM, or for how long.
                The project already knew the consent text was unwritten: that
                is question B5 of docs/COMPLIANCE_OPEN_QUESTIONS.md. The screen
                did not say so; it presented one sentence as if it were the
                whole notice.

                What follows asserts NOTHING legal (§10): these are the facts
                verifiable in the code. And the last line says plainly what is
                missing rather than inventing it.
            --}}
            <details class="field">
                <summary>{{ __('auth.privacy_summary') }}</summary>
                <ul>
                    <li>{!! __('auth.privacy_who', [
                        'officer' => '<strong>'.e(__('auth.privacy_who_officer')).'</strong>',
                        'mayor' => '<strong>'.e(__('auth.privacy_who_mayor')).'</strong>',
                    ]) !!}</li>
                    <li>{!! __('auth.privacy_id', ['strong' => '<strong>'.e(__('auth.privacy_id_strong')).'</strong>']) !!}</li>
                    <li>{!! __('auth.privacy_photos', ['strong' => '<strong>'.e(__('auth.privacy_photos_strong')).'</strong>']) !!}</li>
                    <li>{!! __('auth.privacy_audit', ['strong' => '<strong>'.e(__('auth.privacy_audit_strong')).'</strong>']) !!}</li>
                </ul>
                <p class="u-note">
                    <strong>{{ __('auth.privacy_pending_label') }}</strong>
                    {{ __('auth.privacy_pending') }}
                </p>
            </details>

            <x-button type="submit" variant="primary" block>{{ __('auth.create_my_account') }}</x-button>
        </form>

        <p class="auth-card__links">
            <a href="{{ route('login') }}">{{ __('auth.already_have_account') }}</a>
        </p>
    </x-auth-card>
@endsection
