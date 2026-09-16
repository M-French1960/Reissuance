@extends('layouts.app')
@section('title', __('auth.reset_title'))

@section('content')
    <x-auth-card :title="__('auth.reset_title')">
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-field name="email" :label="__('auth.email')" type="email" required
                     :value="$request->email" autocomplete="username"
                     :error="$errors->first('email')" />

            <x-field name="password" :label="__('auth.new_password')" type="password" required
                     autocomplete="new-password"
                     :hint="__('auth.password_hint')"
                     :error="$errors->first('password')" />

            <x-field name="password_confirmation" :label="__('auth.confirm_password_field')" type="password"
                     required autocomplete="new-password" />

            <x-button type="submit" variant="primary" block>{{ __('common.save') }}</x-button>
        </form>
    </x-auth-card>
@endsection
