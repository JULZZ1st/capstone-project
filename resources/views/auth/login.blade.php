@extends('layouts.guest')

@section('title', 'Login | Barangay Donation Platform')

@section('content')
    <x-auth-card>
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-green-700">Barangay Donation Platform</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">Sign In</h1>
        </div>

        @if (session('status'))
            <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->has('auth'))
            <x-alert type="error" class="mb-4">{{ $errors->first('auth') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <x-form.input
                name="email"
                label="Email"
                type="email"
                required
                autofocus
                autocomplete="email"
            />

            <x-form.input
                name="password"
                label="Password"
                type="password"
                required
                autocomplete="current-password"
            />

            <x-form.checkbox name="remember" label="Remember me" />

            <x-button>Sign In</x-button>
        </form>

    </x-auth-card>
@endsection
