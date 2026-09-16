@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'error' => null,
])

@php
    $id = $attributes->get('id', $name);
    $hintId = $hint ? $id.'-hint' : null;
    $errorId = $error ? $id.'-error' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div class="field">
    <label class="field__label" for="{{ $id }}">
        {{ $label }}
        @if ($required)
            <span aria-hidden="true">*</span>
            <span class="visually-hidden">({{ __('common.required_field') }})</span>
        @endif
    </label>

    @if ($hint)
        <span class="field__hint" id="{{ $hintId }}">{{ $hint }}</span>
    @endif

    <input
        class="field__control"
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('id') }}
    >

    {{-- Tied to the input through aria-describedby, so a screen reader
         announces the error as the user reaches the field (8.4). --}}
    @if ($error)
        <p class="field__error" id="{{ $errorId }}">{{ $error }}</p>
    @endif
</div>
