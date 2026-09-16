@extends('layouts.app')
@section('title', __('auth.login_title'))

@section('content')
    <x-auth-card :title="__('auth.login_title')" :intro="__('auth.login_intro')">
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <x-field name="email" :label="__('auth.email')" type="email"
                     autocomplete="username" required autofocus
                     :error="$errors->first('email')" />

            <x-field name="password" :label="__('auth.password')" type="password"
                     autocomplete="current-password" required
                     :error="$errors->first('password')" />

            <div class="field field--inline">
                <input type="checkbox" id="remember" name="remember" class="field__checkbox">
                <label for="remember">{{ __('auth.remember') }}</label>
            </div>

            <x-button type="submit" variant="primary" block>{{ __('common.sign_in') }}</x-button>
        </form>

        <p class="auth-card__links">
            <a href="{{ route('password.request') }}">{{ __('auth.forgot') }}</a>
            <span aria-hidden="true">&middot;</span>
            <a href="{{ route('register') }}">{{ __('auth.register_link') }}</a>
        </p>
    </x-auth-card>
@endsection
