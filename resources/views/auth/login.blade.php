@extends('layouts.guest')

@section('title', 'Login | Barangay Donation Platform')

@section('content')
    <x-auth-card>
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-green-700">Barangay Donation Platform</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">Sign in to your account</h1>
            <p class="mt-2 text-sm text-slate-600">Access donation records, inventory workflows, cash transactions, and reconciliation tools.</p>
        </div>

        @if (session('status'))
            <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->has('email'))
            <x-alert type="error" class="mb-4">{{ $errors->first('email') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <x-form.input
                name="email"
                label="Email Address"
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

            <x-form.checkbox name="remember" label="Remember me on this device" />

            <x-button>Sign In</x-button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Need an account?
            <a href="{{ route('register') }}" class="font-semibold text-blue-700 hover:text-blue-800">Sign up</a>
        </p>
    </x-auth-card>
@endsection
