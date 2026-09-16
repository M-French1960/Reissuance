@extends('layouts.app')
@section('title', $steps[$step])

@section('content')
    <h1>{{ __('wizard.heading') }}</h1>
    <p class="field__hint">{{ __('wizard.reference_line', ['reference' => $draft->reference]) }}</p>

    <x-step-indicator :steps="$steps" :current="$step" />

    <x-flash />

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">
                {{ $errors->count() === 1 ? __('wizard.errors_one') : __('wizard.errors_many', ['count' => $errors->count()]) }}
            </p>
            <ul class="alert__list">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @yield('wizard')
@endsection
