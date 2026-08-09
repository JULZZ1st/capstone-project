@props(['message' => 'This dashboard section is temporarily unavailable.'])

<div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    <x-icon name="alert-circle" class="h-5 w-5 shrink-0" />
    <span>{{ $message }}</span>
</div>
