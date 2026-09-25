@extends('layouts.auth')
@section('title', __('auth.forgot_title'))

@section('aside_title', __('auth.recover_aside_title'))
@section('aside_lead', __('auth.recover_aside_lead'))

@section('card_title', __('auth.forgot_title'))
@section('card_intro', __('auth.forgot_intro'))

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-field name="email" :label="__('auth.email')" type="email" required autofocus
                 autocomplete="username" :error="$errors->first('email')" />
        <x-button type="submit" variant="primary" block>{{ __('auth.send_link') }}</x-button>
    </form>

    <p class="auth__links"><a href="{{ route('login') }}">{{ __('auth.back_to_login') }}</a></p>
@endsection
