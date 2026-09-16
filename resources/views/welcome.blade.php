@extends('layouts.app')
@section('title', __('home.title'))

@section('content')
    {{--
        THIS PAGE SPEAKS TO A CITIZEN, NOT TO A DEVELOPER (D-072).

        It long announced "Technical foundation, milestone 1" and offered two
        links: the service status and the component gallery. The first page of
        the service told the citizen the service did not exist yet, and gave
        them no way in.

        What it has to do, in this order: say what can be done here, say what
        to have to hand, and give the way in.
    --}}
    <h1>{{ __('home.heading') }}</h1>
    <p class="lede">{{ __('home.lede') }}</p>

    @unless ($signatureEngage)
        {{--
            SAID ON THE HOME PAGE, not at the end of the journey (D-025, §10).

            While the signature provider is the demonstration adapter, every
            certificate issued carries "no legal value". Letting a citizen go
            all the way through to receive an unusable document would be
            deceiving them.
        --}}
        <x-alert variant="danger" title="{{ __('home.demo_title') }}">
            {!! __('home.demo_body', ['mention' => '<strong>'.e(__('home.demo_mention')).'</strong>']) !!}
        </x-alert>
    @endunless

    <div class="grid grid--2">
        <x-card :title="__('home.apply_title')">
            <p>{{ __('home.apply_body') }}</p>
            <div class="row-actions">
                <x-button href="{{ route('register') }}" variant="primary">{{ __('common.create_account') }}</x-button>
                <x-button href="{{ route('login') }}" variant="secondary">{{ __('home.have_account') }}</x-button>
            </div>
        </x-card>

        <x-card :title="__('home.bring_title')">
            <ul>
                <li>{!! __('home.bring_id', ['strong' => '<strong>'.e(__('home.bring_id_strong')).'</strong>']) !!}</li>
                <li>{!! __('home.bring_photo', ['strong' => '<strong>'.e(__('home.bring_photo_strong')).'</strong>']) !!}</li>
                <li>{!! __('home.bring_details', ['strong' => '<strong>'.e(__('home.bring_details_strong')).'</strong>']) !!}</li>
            </ul>
            <p class="u-note">{{ __('home.bring_note') }}</p>
        </x-card>
    </div>

    <x-card :title="__('home.how_title')">
        {{-- The four steps from the applicant's point of view: what they do,
             what the administration does, then what they receive. --}}
        <ol class="steps-plain">
            <li>{{ __('home.how_1') }}</li>
            <li>{{ __('home.how_2') }}</li>
            <li>{{ __('home.how_3') }}</li>
            <li>{{ __('home.how_4') }}</li>
        </ol>
        <p class="u-note">{{ __('home.how_note') }}</p>
    </x-card>

    <x-card :title="__('home.staff_title')">
        <p>{{ __('home.staff_body') }}</p>
        <div class="row-actions">
            <x-button href="{{ route('login') }}" variant="secondary">{{ __('common.sign_in') }}</x-button>
            <x-button href="{{ route('health') }}" variant="secondary">{{ __('common.service_status') }}</x-button>
            @if (Route::has('dev.ui'))
                <x-button href="{{ route('dev.ui') }}" variant="secondary">{{ __('common.component_gallery') }}</x-button>
            @endif
        </div>
    </x-card>
@endsection
