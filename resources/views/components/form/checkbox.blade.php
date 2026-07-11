@props(['name', 'label'])

<div>
    <label for="{{ $name }}" class="flex items-start gap-3 text-sm text-slate-700">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="checkbox"
            value="1"
            @checked(old($name))
            {{ $attributes->merge(['class' => 'mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-500']) }}
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        >
        <span>{{ $label }}</span>
    </label>
    <x-form.error :name="$name" />
</div>
