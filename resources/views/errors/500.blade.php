@extends('errors.layout', ['titre' => __('errors.500.title')])

@section('explication')
    <p>{{ __('errors.500.body') }}</p>
    <p class="u-note">{{ __('errors.500.note') }}</p>
@endsection
