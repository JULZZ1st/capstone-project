<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('dashboard.barangay_name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <x-app.sidebar />

        <div class="min-w-0 flex-1">
            <x-app.header :title="$title ?? trim($__env->yieldContent('page-title', 'Dashboard'))" />

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                @if (session('success') || session('status'))
                    <x-alert type="success" class="mb-6">{{ session('success') ?? session('status') }}</x-alert>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
