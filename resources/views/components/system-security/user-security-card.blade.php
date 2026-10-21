@props(['user', 'permissions'])

@php
    $profile = $user->securityProfile;
    $twoFactorEnabled = (bool) $profile?->two_factor_enabled;
    $twoFactorEnforced = (bool) $profile?->two_factor_enforced;
@endphp

@php
    $isDeactivated = isset($user->status) && $user->status !== config('auth_defaults.user_status');
@endphp

<article {{ $attributes->merge(['class' => 'rounded-lg border p-4 shadow-sm']) }}
    style="background-color: {{ $isDeactivated ? '#f8fafc' : 'white' }}; border-color: {{ $isDeactivated ? '#fde68a' : '#e2e8f0' }}">
    <div class="flex items-start gap-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
            {{ strtoupper(substr($user->first_name ?: $user->name, 0, 1).substr($user->last_name ?: $user->name, 0, 1)) }}
        </div>
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-slate-950">{{ $user->name }}</p>
            <x-system-security.status-badge :label="$user->roleDisplayName()" tone="emerald" class="mt-1" />
            @if($isDeactivated)
                <p class="mt-1 text-xs font-semibold text-rose-700">Deactivated</p>
                <p class="mt-1 text-xs text-slate-500">This account is disabled and retained for audit history. Use Edit to reactivate it.</p>
            @endif
        </div>
    </div>

    <div class="mt-4 rounded-md border border-emerald-100 bg-emerald-50 px-3 py-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">2FA Authorization</p>
                <p class="text-xs font-semibold text-slate-950">
                    {{ $twoFactorEnforced ? 'TOTP Enforced' : ($twoFactorEnabled ? 'Enabled, Not Enforced' : 'Not Enabled') }}
                </p>
            </div>
            <form method="POST" action="{{ route('system-security.two-factor-enforcement.update', $user) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enforced" value="{{ $twoFactorEnforced ? '0' : '1' }}">
                <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-[10px] font-bold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    {{ $twoFactorEnforced ? 'Relax Enforce' : 'Toggle Enforce' }}
                </button>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <p class="mb-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Access Clearance Controls:</p>
        <div class="max-h-28 overflow-y-auto pr-2">
            <x-system-security.permission-checklist :permissions="$permissions" :assigned-permissions="$user->securityPermissions" />
        </div>
    </div>

    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Simulated Key</p>
        <p class="font-mono text-[10px] font-bold text-slate-700">{{ $profile?->security_key_suffix ?? '------' }}</p>
    </div>

    @can('updateSecuritySettings', \App\Models\SecurityActivityEvent::class)
    <div class="mt-3 flex items-center justify-end gap-2">
        <button type="button" class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold hover:bg-slate-200" data-dialog-open="edit-staff-account-modal-{{ $user->id }}">Edit</button>
        <dialog id="edit-staff-account-modal-{{ $user->id }}" class="m-auto w-[calc(100%-2rem)] max-w-lg overflow-hidden rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/55" @if (old('_modal') === 'edit-'.$user->id) open @endif>
            <form method="POST" action="{{ route('system-security.users.update', $user) }}" class="flex max-h-[86vh] flex-col bg-white">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_modal" value="edit-{{ $user->id }}">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
                    <div>
                        <h2 id="edit-staff-account-title-{{ $user->id }}" class="text-lg font-semibold text-slate-950">Edit Account</h2>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $user->email }}</p>
                    </div>
                    <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800" data-dialog-close="edit-staff-account-modal-{{ $user->id }}" aria-label="Close modal"><x-icon name="x-circle" class="h-5 w-5" /></button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                    <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] text-slate-500">First name</label>
                        <input name="first_name" required value="{{ old('first_name', $user->first_name) }}" class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500">Last name</label>
                        <input name="last_name" required value="{{ old('last_name', $user->last_name) }}" class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500">Email</label>
                        <input name="email" type="email" required value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded border px-2 py-1 text-sm" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500">Role</label>
                        <select name="role" required class="mt-1 w-full rounded border px-2 py-1 text-sm">
                            <option value="secretary" {{ $user->roleName() === 'secretary' ? 'selected' : '' }}>Secretary</option>
                            <option value="treasurer" {{ $user->roleName() === 'treasurer' ? 'selected' : '' }}>Treasurer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500">Status</label>
                        <select name="status" class="mt-1 w-full rounded border px-2 py-1 text-sm">
                            <option value="{{ config('auth_defaults.user_status') }}" {{ ! $isDeactivated ? 'selected' : '' }}>Active</option>
                            <option value="deactivated" {{ $isDeactivated ? 'selected' : '' }}>Deactivated</option>
                        </select>
                    </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                    <button type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-close="edit-staff-account-modal-{{ $user->id }}">Cancel</button>
                    <button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Save</button>
                </div>
            </form>
        </dialog>

        @unless($isDeactivated)
            <form method="POST" action="{{ route('system-security.users.deactivate', $user) }}" onsubmit="return confirm('Are you sure you want to deactivate this account? This will disable the account and may prevent access.')">
                @csrf
                @method('PATCH')
                <button class="rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white">Deactivate</button>
            </form>
        @endunless
    </div>
    @endcan
</article>
