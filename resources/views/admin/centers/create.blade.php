@extends('layouts.app')
@section('title', __('admin.centers.connect_heading'))

@section('content')
    <h1>{{ __('admin.centers.connect_heading') }}</h1>

    <x-card :title="__('admin.centers.what_title')">
        <p>{{ __('admin.centers.what_body') }}</p>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('admin.centers.store') }}">
            @csrf
            @include('admin.centers._form', ['communes' => $communes])
                <x-button type="submit" variant="primary">{{ __('admin.centers.connect_button') }}</x-button>
                <x-button href="{{ route('admin.centers.index') }}" variant="secondary">{{ __('common.cancel') }}</x-button>
        </form>
    </x-card>
@endsection
