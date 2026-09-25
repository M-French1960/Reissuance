@extends('layouts.auth')
@section('title', __('auth.twofa_title'))

@section('aside_title', __('auth.login_aside_title'))
@section('aside_lead', __('auth.login_aside_lead'))

@section('card_title', __('auth.twofa_title'))
@section('card_intro', __('auth.twofa_intro'))

@section('content')
    <form method="POST" action="{{ route('two-factor.login') }}">
        @csrf
        <x-field name="code" :label="__('auth.twofa_code')" required autofocus
                 autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*"
                 :error="$errors->first('code')" />
        <x-button type="submit" variant="primary" block>{{ __('auth.twofa_validate') }}</x-button>
    </form>

    <hr class="review-divider">

    <details>
        <summary>{{ __('auth.twofa_no_app') }}</summary>
        <p class="field__hint">{{ __('auth.twofa_recovery_note') }}</p>
    <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf
            <x-field name="recovery_code" :label="__('auth.twofa_recovery_code')"
                     autocomplete="one-time-code" :error="$errors->first('recovery_code')" />
            <x-button type="submit" variant="secondary" block>{{ __('auth.twofa_use_code') }}</x-button>
        </form>
    </details>
@endsection
