@extends('errors.layout', ['titre' => __('errors.419.title')])

@section('explication')
    <p>{{ __('errors.419.body') }}</p>
    <p class="u-note">{{ __('errors.419.note') }}</p>
@endsection
