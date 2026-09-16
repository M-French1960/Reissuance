@extends('layouts.app')
@section('title', $titre)

@section('content')
    {{--
        ERROR PAGES ARE PAGES OF THE SERVICE (D-073).

        They did not exist: Laravel rendered its own default, with no header,
        no footer and not a word of the service's language, a blank screen
        reading "Not Found". A citizen who mistyped an address, or followed a
        stale link, was left with nothing: no explanation and no way back.

        WHAT THEY DO NOT SAY, deliberately: why access was refused. A 404
        served in place of a 403 must teach nothing, neither that a file
        exists nor how many. See ActDocumentController.
    --}}
    <h1>{{ $titre }}</h1>

    <x-card>
        {{ $slot ?? '' }}
        @yield('explication')

        <div class="row-actions u-stack-top">
            @auth
                <x-button href="{{ route('dashboard') }}" variant="primary">{{ __('errors.back_to_account') }}</x-button>
            @else
                <x-button href="{{ route('home') }}" variant="primary">{{ __('errors.back_home') }}</x-button>
                <x-button href="{{ route('login') }}" variant="secondary">{{ __('common.sign_in') }}</x-button>
            @endauth
        </div>
    </x-card>
@endsection
