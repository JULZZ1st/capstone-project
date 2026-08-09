@props(['title' => 'Dashboard'])

@php
    $user = auth()->user();
    $roleName = $user?->roleDisplayName() ?? 'User';
@endphp

<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold text-slate-950">{{ $title }}</h1>
        </div>

        <details class="group relative">
            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-full px-2 py-1.5 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <span class="hidden text-right sm:block">
                    <span class="block text-sm font-semibold text-slate-900">{{ $user?->name }}</span>
                    <span class="block text-xs text-slate-500">{{ $roleName }}</span>
                </span>
                <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-sm font-semibold text-white shadow-sm">
                    {{ strtoupper(substr((string) $user?->name, 0, 1)) }}
                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500"></span>
                </span>
                <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-400 transition group-open:rotate-180 sm:block" />
            </summary>
            <div class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg shadow-slate-900/5">
                <div class="border-b border-slate-100 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-900">{{ $user?->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user?->email }}</p>
                    <p class="mt-1.5 inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{{ $roleName }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50">
                        <x-icon name="logout" class="h-4 w-4" />
                        Logout
                    </button>
                </form>
            </div>
        </details>
    </div>
</header>
