@props(['label', 'icon' => null, 'danger' => false])
<button type="button" {{ $attributes->class(['data-table__action', 'data-table__action--danger' => $danger]) }} aria-label="{{ $label }}" title="{{ $label }}">
    @if ($icon)<x-icon :name="$icon" class="h-4 w-4" />
    @endif
    @if ($slot->isNotEmpty()){{ $slot }}@else<span class="sr-only">{{ $label }}</span>
    @endif
</button>
