@extends('layouts.app')
@section('title', __('auth.confirm_title'))

@section('content')
    <x-auth-card :title="__('auth.confirm_title')" :intro="__('auth.confirm_intro')">
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <x-field name="password" :label="__('auth.password')" type="password" required autofocus
                     autocomplete="current-password" :error="$errors->first('password')" />
            <x-button type="submit" variant="primary" block>{{ __('auth.confirm') }}</x-button>
        </form>
    </x-auth-card>
@endsection
