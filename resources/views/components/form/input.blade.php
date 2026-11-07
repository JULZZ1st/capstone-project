@props(['name', 'label', 'type' => 'text', 'value' => null, 'id' => null, 'useOld' => true])

@php($inputId = $id ?? $name)

<div>
    <x-form.label :for="$inputId">{{ $label }}</x-form.label>
    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : ($useOld ? old($name, $value) : $value) }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    <x-form.error :name="$name" />
</div>
