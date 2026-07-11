@props(['name'])

@error($name)
    <p class="mt-1 text-sm text-red-600" id="{{ $name }}-error">{{ $message }}</p>
@enderror
