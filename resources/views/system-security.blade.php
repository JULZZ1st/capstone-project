@extends('layouts.app')

@section('title', 'System Security Administration | '.config('dashboard.barangay_name'))
@section('page-title', 'System Security Administration')
@section('page-subtitle', 'Oversee two-factor lockouts, staff permissions, and non-repudiation audit ledgers.')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                <span class="rounded bg-slate-200 px-2 py-1 font-semibold text-slate-700">{{ config('dashboard.barangay_name') }} Node</span>
                <span>/</span>
                <span>Security:</span>
                <x-system-security.status-badge label="Two-Factor Verified" tone="emerald" />
            </div>

            <div class="flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <span>Admin Control Console</span>
                <x-system-security.status-badge :label="auth()->user()?->roleDisplayName() ?? 'User'" tone="blue" />
            </div>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <x-system-security.section-header title="Authorized Administrative Personnel">
                    <x-slot:actions>
                        <x-system-security.status-badge :label="number_format($personnel->where('status', config('auth_defaults.user_status'))->count()).' Active Accounts'" tone="emerald" />

                        @can('updateSecuritySettings', \App\Models\SecurityActivityEvent::class)
                        <details id="create-staff-account-details" class="relative ml-2 inline-block" @if (old('_modal') === 'create') open @endif>
                            <summary class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold">Create Staff Account</summary>
                            <form method="POST" action="{{ route('system-security.users.store') }}" class="mt-2 w-80 rounded border border-slate-100 bg-white p-3 shadow-lg">
                                @csrf
                                <input type="hidden" name="_modal" value="create">
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[11px] text-slate-500">First name</label>
                                        <input name="first_name" value="{{ old('first_name') }}" required class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                                        <x-form.error name="first_name" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500">Last name</label>
                                        <input name="last_name" value="{{ old('last_name') }}" required class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                                        <x-form.error name="last_name" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500">Email</label>
                                        <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                                        <x-form.error name="email" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500">Role</label>
                                        <select name="role" required class="mt-1 w-full rounded border px-2 py-1 text-sm">
                                            <option value="secretary" @selected(old('role') === 'secretary')>Secretary</option>
                                            <option value="treasurer" @selected(old('role') === 'treasurer')>Treasurer</option>
                                        </select>
                                        <x-form.error name="role" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500">Initial password</label>
                                        <input name="password" type="password" required class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                                        <x-form.error name="password" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500">Confirm password</label>
                                        <input name="password_confirmation" type="password" required class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                                    </div>
                                    <div class="mt-2 flex justify-end">
                                        <button class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">Create</button>
                                    </div>
                                </div>
                            </form>
                        </details>
                        @endcan
                    </x-slot:actions>
            </x-system-security.section-header>

            <div class="grid gap-4 p-4 lg:grid-cols-3">
                @forelse ($personnel as $user)
                    <x-system-security.user-security-card :user="$user" :permissions="$permissions" />
                @empty
                    <div class="lg:col-span-3">
                        <x-dashboard.empty-state message="No authorized administrative personnel were found." />
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <x-system-security.section-header title="Security Activity Trail Ledger">
                <x-slot:actions>
                    <x-system-security.search-bar :filters="$filters" :actions="$actionOptions" />
                </x-slot:actions>
            </x-system-security.section-header>

            <div class="max-h-[420px] overflow-y-auto">
                @forelse ($activityEvents as $event)
                    <x-system-security.activity-log-row :event="$event" />
                @empty
                    <x-dashboard.empty-state message="No security activity logs match the current filters." class="m-5" />
                @endforelse
            </div>

            @if ($activityEvents->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $activityEvents->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
