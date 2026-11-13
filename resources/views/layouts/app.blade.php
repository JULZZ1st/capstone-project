<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('dashboard.barangay_name'))</title>
    <script>
        // Apply the persisted preference before the sidebar can paint in its default state.
        document.documentElement.classList.add('sidebar-preload');
        try {
            if (['1', 'true'].includes(localStorage.getItem('sidebarCollapsed'))) {
                document.documentElement.dataset.sidebarPreference = 'collapsed';
            }
        } catch (error) {}
    </script>
    <style>
        html.sidebar-preload #app-sidebar,
        html.sidebar-preload #app-sidebar * { transition: none !important; animation: none !important; }
        @media (min-width: 1024px) {
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar { width: 5.25rem; flex-basis: 5.25rem; }
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar #sidebar-logo { justify-content: center; }
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar .sidebar-label,
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar .sidebar-logo-text { opacity: 0; max-width: 0; }
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar .nav-item,
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar .border-t button { justify-content: center; gap: 0; }
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar #sidebar-toggle { opacity: 0; pointer-events: none; }
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar .sidebar-expanded-icon { display: none; }
            html.sidebar-preload[data-sidebar-preference='collapsed'] #app-sidebar .sidebar-collapsed-icon { display: block; }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-x-hidden bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <x-app.sidebar />

        <div class="min-w-0 flex-1 overflow-x-hidden">
            <x-app.header
                :title="$title ?? trim($__env->yieldContent('page-title', 'Dashboard'))"
                :subtitle="$subtitle ?? trim($__env->yieldContent('page-subtitle', ''))"
            />

            <main class="min-w-0 w-full px-4 py-6 sm:px-5 lg:px-6">

                @yield('content')
            </main>
        </div>
    </div>

    @if (session('success') || session('status'))
        <div
            data-toast
            role="status"
            aria-live="polite"
            class="fixed right-4 top-4 z-[110] max-w-sm rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-medium text-emerald-800 shadow-lg ring-1 ring-black/5 transition duration-300 sm:right-6 sm:top-6"
        >
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">✓</span>
                <span>{{ session('success') ?? session('status') }}</span>
            </div>
        </div>
    @endif

    @stack('scripts')
</body>
</html>
