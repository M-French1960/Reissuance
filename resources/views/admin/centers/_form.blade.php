{{--
    Le formulaire de raccordement, partage par la creation et l'edition.

    LE CODE N'EST PAS SEULEMENT DESACTIVE DANS L'INTERFACE quand il est fige :
    le controleur le refuse aussi. Un champ desactive se reactive en trois
    clics dans un navigateur ; ce n'est pas une protection, c'est une
    indication.
--}}
@php
    // Un partiel inclus n'a pas de @props : les valeurs par defaut se posent
    // ici, sinon l'ecran de creation tombe sur une variable indefinie.
    $center ??= null;
    $codeEditable ??= true;
    $receivedCount ??= 0;
@endphp

@if ($errors->any())
    <x-alert variant="danger" :title="__('officer.action_impossible')">
        <ul class="alert__list">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    </x-alert>
@endif

<x-field name="name" :label="__('admin.centers.name')" :value="$center?->name"
         :hint="__('admin.centers.name_hint')" :error="$errors->first('name')" required />

<x-field name="city" :label="__('admin.centers.city')" :value="$center?->city"
         :error="$errors->first('city')" required />

<div class="field">
    <label class="field__label" for="commune_id">
        {{ __('admin.centers.commune') }}
        <span aria-hidden="true">*</span>
        <span class="visually-hidden">({{ __('common.required_field') }})</span>
    </label>
    <span class="field__hint" id="commune_id-hint">{{ __('admin.centers.commune_hint') }}</span>
    <select class="field__control" id="commune_id" name="commune_id" required
            aria-describedby="commune_id-hint @if ($errors->has('commune_id')) commune_id-error @endif"
            @if ($errors->has('commune_id')) aria-invalid="true" @endif>
        <option value="">{{ __('admin.users.choose') }}</option>
        @foreach ($communes as $commune)
            <option value="{{ $commune->id }}" @selected((int) old('commune_id', $center?->commune_id) === $commune->id)>
                {{ $commune->name }}
            </option>
        @endforeach
    </select>
    @error('commune_id')<p class="field__error" id="commune_id-error">{{ $message }}</p>@enderror
</div>

@if ($codeEditable)
    <x-field name="code" :label="__('admin.centers.code')" :value="$center?->code"
             :hint="__('admin.centers.code_hint')" :error="$errors->first('code')"
             required maxlength="32" autocapitalize="characters" />
@else
    <div class="field">
        <span class="field__label" id="code-label">{{ __('admin.centers.code') }}</span>
        <span class="field__hint">{{ __('admin.centers.code_frozen_hint', ['count' => $receivedCount]) }}</span>
        <p class="field__frozen" aria-labelledby="code-label">{{ $center?->code }}</p>
    </div>
@endif

<div class="field field--inline">
    <input type="checkbox" id="is_active" name="is_active" value="1" class="field__checkbox"
           aria-describedby="is_active-hint"
           @checked(old('is_active', $center?->is_active ?? false))>
    <label for="is_active">{{ __('admin.centers.is_active') }}</label>
</div>
<p class="field__hint" id="is_active-hint">{{ __('admin.centers.is_active_hint') }}</p>

<p class="field__hint">{{ __('admin.centers.pickup_missing') }}</p>
