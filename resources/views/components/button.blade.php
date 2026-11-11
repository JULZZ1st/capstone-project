@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'ui-button ui-button--primary w-full']) }}
>
    {{ $slot }}
</button>
