@extends('layouts.guest')

@section('title', 'Sign Up | Barangay Donation Platform')

@section('content')
    <div class="grid w-full max-w-5xl overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200 lg:grid-cols-[0.9fr_1.1fr]">
        <section class="bg-slate-900 px-6 py-8 text-white sm:px-8 lg:flex lg:flex-col lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-green-300">Barangay Donation Platform</p>
                <h1 class="mt-4 text-3xl font-bold">Community aid, organized with care.</h1>
                <p class="mt-4 text-sm leading-6 text-slate-300">Create an account for barangay donation operations, record keeping, inventory coordination, and cash workflows.</p>
            </div>
            <div class="mt-8 grid grid-cols-3 gap-3 text-center text-xs text-slate-300">
                <div class="rounded-md border border-white/10 bg-white/5 p-3">Donors</div>
                <div class="rounded-md border border-white/10 bg-white/5 p-3">Inventory</div>
                <div class="rounded-md border border-white/10 bg-white/5 p-3">Cash</div>
            </div>
        </section>

        <section class="px-6 py-8 sm:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-950">Create your account</h2>
                <p class="mt-2 text-sm text-slate-600">Use your official account details so activity can be attributed clearly.</p>
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

                <x-form.checkbox name="terms" label="I accept the Terms & Conditions for responsible platform use." required />

                <x-button>Create Account</x-button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-800">Sign in</a>
            </p>
        </section>
    </div>
@endsection
