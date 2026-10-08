@extends('layouts.guest')

@section('title', 'Set New Password | Barangay Donation Platform')

@section('content')
    <x-auth-card>
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-green-700">Password update required</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">Set a new password</h1>
            <p class="mt-2 text-sm text-slate-600">Use your temporary password, then choose a new password for future sign-ins.</p>
        </div>

        @if ($errors->any())
            <x-alert type="error" class="mb-4">{{ $errors->first() }}</x-alert>
        @endif

        <form method="POST" action="{{ route('password.change.store') }}" class="space-y-4">
            @csrf
            <x-form.input name="current_password" label="Current / temporary password" type="password" required autocomplete="current-password" />
            <x-form.input name="password" label="New password" type="password" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
            <x-button>Save New Password</x-button>
        </form>
    </x-auth-card>
@endsection
