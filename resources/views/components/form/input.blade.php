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
        @if($useOld && $errors->has($name)) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
    >
    @if($useOld)
        <x-form.error :name="$name" :id="$inputId.'-error'" />
    @endif
</div>
