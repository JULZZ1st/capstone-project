@props(['name', 'label', 'type' => 'text', 'value' => null, 'id' => null, 'useOld' => true])

@php($inputId = $id ?? $name)

<div>
    <x-form.label :for="$inputId">{{ $label }}</x-form.label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : ($useOld ? old($name, $value) : $value) }}"
        {{ $attributes->merge(['class' => 'ui-control mt-1 block w-full']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    <x-form.error :name="$name" />
</div>
