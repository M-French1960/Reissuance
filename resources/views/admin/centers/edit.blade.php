@extends('layouts.app')
@section('title', __('admin.centers.edit_heading', ['name' => $center->name]))

@section('content')
    <h1>{{ __('admin.centers.edit_heading', ['name' => $center->name]) }}</h1>

    <x-card :title="__('admin.centers.what_title')">
        <p>{{ __('admin.centers.connected_note') }}</p>
        <p>{{ __('admin.centers.disconnected_note') }}</p>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('admin.centers.update', $center) }}">
            @csrf
            @method('PATCH')
            @include('admin.centers._form', [
                'communes' => $communes,
                'center' => $center,
                'codeEditable' => $codeEditable,
                'receivedCount' => $received,
            ])
                <x-button type="submit" variant="primary">{{ __('admin.centers.save') }}</x-button>
                <x-button href="{{ route('admin.centers.index') }}" variant="secondary">{{ __('common.cancel') }}</x-button>
        </form>
    </x-card>
@endsection
