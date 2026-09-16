@extends('errors.layout', ['titre' => __('errors.403.title')])

@section('explication')
    <p>{{ __('errors.403.body') }}</p>
@endsection
