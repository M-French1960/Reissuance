@extends('layouts.app')
@section('title', __('citizen.profile.title'))

@section('content')
    <h1>{{ __('citizen.profile.title') }}</h1>
    <p>{{ __('citizen.profile.intro') }}</p>

    <x-flash />

    @if ($errors->any())
        <div class="alert alert--danger" role="alert">
            <p class="alert__title">
                {{ $errors->count() === 1 ? __('wizard.errors_one') : __('wizard.errors_many', ['count' => $errors->count()]) }}
            </p>
            <ul class="alert__list">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('citizen.profile.update') }}">
            @csrf @method('PATCH')

            <div class="grid grid--2">
                <x-field name="first_name" :label="__('citizen.profile.first_name')" required :value="$profile->first_name"
                         autocomplete="given-name" :error="$errors->first('first_name')" />
                <x-field name="last_name" :label="__('citizen.profile.last_name')" required :value="$profile->last_name"
                         autocomplete="family-name" :error="$errors->first('last_name')" />
            </div>

            <div class="grid grid--2">
                <x-field name="birth_date" :label="__('citizen.profile.birth_date')" type="date" required
                         :value="$profile->birth_date?->format('Y-m-d')" autocomplete="bday"
                         :error="$errors->first('birth_date')" />
                <x-field name="birth_place" :label="__('citizen.profile.birth_place')" required
                         :value="$profile->birth_place" :error="$errors->first('birth_place')" />
            </div>

            <x-field name="national_id_number" :label="__('citizen.profile.national_id')"
                     :value="$profile->national_id_number"
                     :hint="__('citizen.profile.national_id_hint')"
                     autocomplete="off"
                     :error="$errors->first('national_id_number')" />

            <div class="grid grid--2">
                <x-field name="phone" :label="__('citizen.profile.phone')" type="tel" required :value="$profile->phone"
                         autocomplete="tel" :hint="__('citizen.profile.phone_hint')"
                         :error="$errors->first('phone')" />
                <x-field name="address" :label="__('citizen.profile.address')" required :value="$profile->address"
                         autocomplete="street-address" :error="$errors->first('address')" />
            </div>

            <x-button type="submit" variant="primary">{{ __('common.save') }}</x-button>
        </form>
    </x-card>
@endsection
