@extends('layouts.app')
@section('title', __('admin.users.create'))

@section('content')
    <h1>{{ __('admin.users.create_heading') }}</h1>

    <x-alert variant="attention" :title="__('admin.users.create_what_title')">
        {!! __('admin.users.create_what_body', ['strong' => '<strong>'.e(__('admin.users.create_what_strong')).'</strong>']) !!}
    </x-alert>

    <x-card>
        @if ($errors->any())
            <x-alert variant="danger" :title="__('admin.users.not_created_title')">
                <ul class="alert__list">
                    @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <x-field name="name" :label="__('admin.users.full_name')" required :error="$errors->first('name')" />
            <x-field name="email" :label="__('admin.users.email')" type="email" required
                     :error="$errors->first('email')" />

            <div class="field">
                <label class="field__label" for="role">{{ __('admin.users.role') }} <span aria-hidden="true">*</span></label>
                <select class="field__control" id="role" name="role" required>
                    <option value="officer" @selected(old('role') === 'officer')>{{ __('enums.user_role.officer') }}</option>
                    <option value="mayor" @selected(old('role') === 'mayor')>{{ __('enums.user_role.mayor') }}</option>
                    <option value="admin" @selected(old('role') === 'admin')>{{ __('enums.user_role.admin') }}</option>
                </select>
                <span class="field__hint">{{ __('admin.users.role_hint') }}</span>
            </div>

            <div class="field">
                <label class="field__label" for="civil_status_center_id">{{ __('admin.users.centre_for_officers') }}</label>
                <select class="field__control" id="civil_status_center_id" name="civil_status_center_id">
                    <option value="">{{ __('admin.users.choose') }}</option>
                    @foreach ($centers as $center)
                        <option value="{{ $center->id }}" @selected(old('civil_status_center_id') == $center->id)>
                            {{ $center->situation() }}
                        </option>
                    @endforeach
                </select>
                @if ($errors->has('civil_status_center_id'))
                    <p class="field__error">{{ $errors->first('civil_status_center_id') }}</p>
                @endif
            </div>

            <div class="field">
                <label class="field__label" for="commune_id">{{ __('admin.users.commune_for_mayors') }}</label>
                <select class="field__control" id="commune_id" name="commune_id">
                    <option value="">{{ __('admin.users.choose') }}</option>
                    @foreach ($communes as $commune)
                        <option value="{{ $commune->id }}" @selected(old('commune_id') == $commune->id)>{{ $commune->name }}</option>
                    @endforeach
                </select>
                @if ($errors->has('commune_id'))
                    <p class="field__error">{{ $errors->first('commune_id') }}</p>
                @endif
            </div>

            <x-button type="submit" variant="primary">{{ __('admin.users.create_button') }}</x-button>
            <x-button href="{{ route('admin.users.index') }}" variant="secondary">{{ __('common.cancel') }}</x-button>
        </form>
    </x-card>
@endsection
