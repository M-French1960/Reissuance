@extends('layouts.app')
@section('title', __('auth.forgot_title'))

@section('content')
    <x-auth-card :title="__('auth.forgot_title')" :intro="__('auth.forgot_intro')">
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <x-field name="email" :label="__('auth.email')" type="email" required autofocus
                     autocomplete="username" :error="$errors->first('email')" />
            <x-button type="submit" variant="primary" block>{{ __('auth.send_link') }}</x-button>
        </form>

        <p class="auth-card__links"><a href="{{ route('login') }}">{{ __('auth.back_to_login') }}</a></p>
    </x-auth-card>
@endsection
