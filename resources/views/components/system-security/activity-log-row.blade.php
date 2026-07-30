@props(['event'])

@php
    $actor = $event->actor;
    $user = $event->user;
    $role = $actor?->roleDisplayName() ?? $user?->roleDisplayName() ?? 'System';
@endphp

<article {{ $attributes->merge(['class' => 'border-b border-slate-100 px-5 py-4 last:border-b-0']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-bold text-slate-950">{{ $actor?->name ?? $user?->name ?? 'System Event' }}</p>
                <x-system-security.status-badge :label="$role" tone="blue" />
                <x-system-security.status-badge :label="$event->actionLabel()" tone="emerald" />
            </div>
            @if ($user && (! $actor || $user->isNot($actor)))
                <p class="mt-1 text-xs text-slate-500">Affected account: {{ $user->name }}</p>
            @endif
            <p class="mt-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs italic text-slate-600">
                {{ $event->summary }}
            </p>
        </div>

        <div class="shrink-0 text-left text-xs text-slate-500 sm:text-right">
            <p>{{ $event->occurred_at?->format('Y-m-d H:i:s') }}</p>
            @if ($event->ip_address)
                <p class="mt-1 font-mono">{{ $event->ip_address }}</p>
            @endif
        </div>
    </div>
</article>
