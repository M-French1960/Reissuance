@extends('layouts.app')
@section('title', __('auth.twofa_title'))

@section('content')
    <x-auth-card :title="__('auth.twofa_title')" :intro="__('auth.twofa_intro')">
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
    </x-auth-card>
@endsection
