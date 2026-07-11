@props(['type' => 'submit'])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70']) }}
>
    {{ $slot }}
</button>
