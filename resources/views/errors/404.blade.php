@extends('errors.layout', ['titre' => __('errors.404.title')])

@section('explication')
    <p>{{ __('errors.404.body') }}</p>
    <p class="u-note">{{ __('errors.404.note') }}</p>
@endsection
