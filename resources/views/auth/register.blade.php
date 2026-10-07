@extends('layouts.guest')

@section('title', 'Sign Up | Barangay Donation Platform')

@section('content')
    <section class="w-full max-w-md">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-950">Create your account</h2>
            </div>

            @if ($errors->any())
                <x-alert type="error" class="mb-4">
                    Please review the highlighted fields and try again.
                </x-alert>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="first_name" label="First Name" required autocomplete="given-name" />
                    <x-form.input name="last_name" label="Last Name" required autocomplete="family-name" />
                </div>

                <x-form.input name="email" label="Email Address" type="email" required autocomplete="email" />
                <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" />
                <x-form.input name="password_confirmation" label="Confirm Password" type="password" required autocomplete="new-password" />

                <x-button>Create Account</x-button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-800">Sign in</a>
            </p>
    </section>
@endsection
