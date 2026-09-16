@extends('errors.layout', ['titre' => __('errors.503.title')])

@section('explication')
    <p>{{ __('errors.503.body') }}</p>
@endsection
