@extends('layouts.app')

@section('title', 'System Security Administration | '.config('dashboard.barangay_name'))
@section('page-title', 'System Security Administration')
@section('page-subtitle', 'Manage staff accounts, permissions, and security activity.')

@section('content')
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <x-system-security.section-header title="Authorized Administrative Personnel">
                    <x-slot:actions>
                        <x-system-security.status-badge :label="number_format($personnel->where('status', config('auth_defaults.user_status'))->count()).' Active Accounts'" tone="emerald" />

                        @can('updateSecuritySettings', \App\Models\SecurityActivityEvent::class)
                        <button type="button" class="ml-2 rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold hover:bg-slate-200" data-dialog-open="create-staff-account-modal">Create Staff Account</button>
                        <dialog id="create-staff-account-modal" class="m-auto w-[calc(100%-2rem)] max-w-lg overflow-hidden rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/55" @if (old('_modal') === 'create') open @endif>
                            <form method="POST" action="{{ route('system-security.users.store') }}" class="flex max-h-[86vh] flex-col bg-white">
                                @csrf
                                <input type="hidden" name="_modal" value="create">
                                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
                                    <div>
                                        <h2 id="create-staff-account-title" class="text-lg font-semibold text-slate-950">Create Staff Account</h2>
                                        <p class="mt-1 text-sm text-slate-500">Add a Secretary or Treasurer to this workspace.</p>
                                    </div>
                                    <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800" data-dialog-close="create-staff-account-modal" aria-label="Close modal"><x-icon name="x-circle" class="h-5 w-5" /></button>
                                </div>
                                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                                    <div class="space-y-3">
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
                                            <option value="secretary"@if(old('role') === 'secretary') selected @endif>Secretary</option>
                                            <option value="treasurer"@if(old('role') === 'treasurer') selected @endif>Treasurer</option>
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
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                                    <button type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-close="create-staff-account-modal">Cancel</button>
                                    <button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Create</button>
                                </div>
                            </form>
                        </dialog>
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
