@props(['title' => 'Dashboard'])

@php
    $user = auth()->user();
    $roleName = $user?->roleDisplayName() ?? 'User';
@endphp

<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-green-700">{{ config('dashboard.barangay_name') }}</p>
            <h1 class="truncate text-xl font-semibold text-slate-950">{{ $title }}</h1>
        </div>

        <details class="relative">
            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-md px-2 py-1.5 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <span class="hidden text-right sm:block">
                    <span class="block text-sm font-semibold text-slate-900">{{ $user?->name }}</span>
                    <span class="block text-xs text-slate-500">{{ $roleName }}</span>
                </span>
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-700 text-sm font-semibold text-white">
                    {{ strtoupper(substr((string) $user?->name, 0, 1)) }}
                </span>
            </summary>
            <div class="absolute right-0 mt-2 w-56 rounded-md border border-slate-200 bg-white p-2 shadow-lg">
                <div class="border-b border-slate-100 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-900">{{ $user?->name }}</p>
                    <p class="text-xs text-slate-500">{{ $user?->email }}</p>
                    <p class="mt-1 text-xs font-medium text-green-700">{{ $roleName }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button class="w-full rounded px-3 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50">
                        Logout
                    </button>
                </form>
            </div>
        </details>
    </div>
</header>
