@props(['user', 'permissions'])

@php
    $profile = $user->securityProfile;
    $twoFactorEnabled = (bool) $profile?->two_factor_enabled;
    $twoFactorEnforced = (bool) $profile?->two_factor_enforced;
@endphp

<article {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white p-4 shadow-sm']) }}>
    <div class="flex items-start gap-3">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
            {{ strtoupper(substr($user->first_name ?: $user->name, 0, 1).substr($user->last_name ?: $user->name, 0, 1)) }}
        </div>
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-slate-950">{{ $user->name }}</p>
            <x-system-security.status-badge :label="$user->roleDisplayName()" tone="emerald" class="mt-1" />
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
</article>
