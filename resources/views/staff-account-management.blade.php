@extends('layouts.app')

@section('title', 'Staff Account Management | '.config('dashboard.barangay_name'))
@section('page-title', 'Staff Account Management')
@section('page-subtitle', 'Create and maintain Admin and Staff accounts using fixed role-based access.')

@section('content')
    @php($createOpen = old('_modal') === 'create' && $errors->any())
    <div class="space-y-5">
        @if ($errors->any())<x-alert type="error">Please review the highlighted account fields and try again.</x-alert>@endif

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <form method="GET" action="{{ route('system-security.index') }}" class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:max-w-xs">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="staff_search" type="search" value="{{ request('staff_search') }}" placeholder="Search staff by name or email" aria-label="Search staff accounts" autocomplete="off" class="block h-10 w-full rounded-lg border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-staff-account-search>
                    </div>
                    <label class="sr-only" for="staff-role-filter">Role</label>
                    <select id="staff-role-filter" name="staff_role" class="h-10 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-staff-account-filter>
                        <option value="">All roles</option>
                        <option value="admin" @selected(request('staff_role') === 'admin')>Admin</option>
                        <option value="staff" @selected(request('staff_role') === 'staff')>Staff</option>
                    </select>
                    <label class="sr-only" for="staff-status-filter">Status</label>
                    <select id="staff-status-filter" name="staff_status" class="h-10 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-staff-account-filter>
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('staff_status') === 'active')>Active</option>
                        <option value="deactivated" @selected(request('staff_status') === 'deactivated')>Deactivated</option>
                    </select>
                </form>
                <button type="button" class="ui-button ui-button--primary w-full shrink-0 sm:w-auto" data-dialog-open="create-staff-account-dialog"><x-icon name="plus" class="h-4 w-4" />Create Staff Account</button>
            </div>

            @if ($personnel->isEmpty())
                <x-dashboard.empty-state message="No Admin or Staff accounts match your search." class="p-8" />
            @else
                <x-data-table caption="Admin and staff accounts" min-width="48rem" max-height="none" class="staff-accounts-table rounded-none border-0 shadow-none">
                    <x-slot:head>
                        <x-data-table.heading>Name</x-data-table.heading><x-data-table.heading>Email</x-data-table.heading><x-data-table.heading>Role</x-data-table.heading><x-data-table.heading>Status</x-data-table.heading><x-data-table.heading align="right">Actions</x-data-table.heading>
                    </x-slot:head>
                    @foreach ($personnel as $user)
                        @php($active = $user->status === config('auth_defaults.user_status'))
                        @php($manageable = auth()->user()?->id !== $user->id && app(\App\Support\Workspaces\WorkspaceAuthorizer::class)->canManageMember(auth()->user(), $user))
                        <tr>
                            <td><span class="font-semibold text-slate-950">{{ $user->name }}</span></td>
                            <td><span class="text-slate-700">{{ $user->email }}</span></td>
                            <td><x-system-security.status-badge :label="$user->roleDisplayName()" tone="blue" /></td>
                            <td><x-system-security.status-badge :label="$active ? 'Active' : 'Deactivated'" :tone="$active ? 'emerald' : 'slate'" /></td>
                            <td><div class="data-table__actions"><details class="relative" data-account-actions><summary class="data-table__action cursor-pointer list-none" aria-label="Actions for {{ $user->name }}">Actions</summary><div class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white p-1.5 text-left shadow-lg">
                                <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="view-staff-{{ $user->id }}">View Details</button>
                                @if ($manageable)
                                    <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50" data-dialog-open="edit-staff-{{ $user->id }}">Edit Account</button>
                                    <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold {{ $active ? 'text-rose-700 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50' }}" data-dialog-open="status-staff-{{ $user->id }}">{{ $active ? 'Deactivate Account' : 'Reactivate Account' }}</button>
                                @endif
                            </div></details></div></td>
                        </tr>
                    @endforeach
                </x-data-table>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <x-system-security.section-header title="Security Activity Trail"><x-slot:actions><x-system-security.search-bar :filters="$filters" :actions="$actionOptions" /></x-slot:actions></x-system-security.section-header>
            <div>@forelse ($activityEvents as $event)<x-system-security.activity-log-row :event="$event" />@empty<x-dashboard.empty-state message="No security activity logs match the current filters." class="m-5" />@endforelse</div>
            @if ($activityEvents->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $activityEvents->links() }}</div>@endif
        </section>
    </div>

    <dialog id="create-staff-account-dialog" class="m-auto w-[min(34rem,calc(100vw-2rem))] rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55" @if($createOpen) open @endif><form method="POST" action="{{ route('system-security.users.store') }}" class="bg-white">@csrf<header class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="text-lg font-semibold text-slate-950">Create Staff Account</h2><p class="text-sm text-slate-500">New accounts receive fixed Staff role permissions.</p></div><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" data-dialog-close="create-staff-account-dialog" aria-label="Close">&times;</button></header><div class="p-5"><x-system-security.staff-account-fields /></div></form></dialog>

    @foreach ($personnel as $user)
        <dialog id="view-staff-{{ $user->id }}" class="m-auto w-[min(30rem,calc(100vw-2rem))] rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55"><div class="bg-white"><header class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">Account Details</h2><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" data-dialog-close="view-staff-{{ $user->id }}" aria-label="Close">&times;</button></header><dl class="grid gap-4 p-5 text-sm sm:grid-cols-2"><div><dt class="text-xs font-semibold text-slate-500">Name</dt><dd class="mt-1 font-medium text-slate-950">{{ $user->name }}</dd></div><div><dt class="text-xs font-semibold text-slate-500">Email</dt><dd class="mt-1 break-all text-slate-950">{{ $user->email }}</dd></div><div><dt class="text-xs font-semibold text-slate-500">Role</dt><dd class="mt-1 text-slate-950">{{ $user->roleDisplayName() }}</dd></div><div><dt class="text-xs font-semibold text-slate-500">Status</dt><dd class="mt-1 text-slate-950">{{ $user->status === config('auth_defaults.user_status') ? 'Active' : 'Deactivated' }}</dd></div><div class="sm:col-span-2"><dt class="text-xs font-semibold text-slate-500">Last login</dt><dd class="mt-1 text-slate-950">{{ $user->last_login_at?->format('M d, Y g:i A') ?? 'No recorded login' }}</dd></div></dl><footer class="flex justify-end border-t border-slate-200 px-5 py-3"><button type="button" class="ui-button ui-button--secondary" data-dialog-close="view-staff-{{ $user->id }}">Close</button></footer></div></dialog>
        @if ($manageable)
            <dialog id="edit-staff-{{ $user->id }}" class="m-auto w-[min(34rem,calc(100vw-2rem))] rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55" @if(old('_modal') === 'edit-'.$user->id && $errors->any()) open @endif><form method="POST" action="{{ route('system-security.users.update', $user) }}" class="bg-white">@csrf @method('PATCH')<header class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">Edit Staff Account</h2><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" data-dialog-close="edit-staff-{{ $user->id }}" aria-label="Close">&times;</button></header><div class="p-5"><x-system-security.staff-account-fields :user="$user" :form-key="'edit-'.$user->id" /></div></form></dialog>
            <dialog id="status-staff-{{ $user->id }}" class="m-auto w-[min(30rem,calc(100vw-2rem))] rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55"><form method="POST" action="{{ route('system-security.users.toggle-status', $user) }}" class="bg-white">@csrf @method('PATCH')<header class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-semibold text-slate-950">{{ $active ? 'Deactivate' : 'Reactivate' }} Staff Account</h2></header><div class="p-5 text-sm leading-6 text-slate-600"><p>Are you sure you want to {{ $active ? 'deactivate' : 'reactivate' }} <strong class="text-slate-950">{{ $user->name }}</strong>?</p><p class="mt-2">{{ $active ? 'Access will be blocked. Historical financial records and audit history remain unchanged.' : 'This restores the account’s system access.' }}</p></div><footer class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4"><button type="button" class="ui-button ui-button--secondary" data-dialog-close="status-staff-{{ $user->id }}">Cancel</button><button type="submit" class="ui-button {{ $active ? 'ui-button--danger' : 'ui-button--primary' }}">{{ $active ? 'Deactivate' : 'Reactivate' }}</button></footer></form></dialog>
        @endif
    @endforeach
@endsection

@push('scripts')
    <script>
        document.querySelectorAll("dialog[id^='status-staff-']").forEach((dialog) => {
            const submit = dialog.querySelector('button[type="submit"]');
            const heading = dialog.querySelector('h2');
            const body = dialog.querySelector('form > div');

            if (!submit || !heading || !body) return;

            const isDeactivation = submit.textContent.trim() === 'Deactivate';
            heading.textContent = isDeactivation ? 'Deactivate Account' : 'Reactivate Account';
            submit.textContent = isDeactivation ? 'Confirm Deactivation' : 'Confirm Reactivation';
            body.innerHTML = isDeactivation
                ? '<p>Are you sure you want to deactivate this account? This user will no longer be able to log in.</p>'
                : '<p>Are you sure you want to reactivate this account?</p>';
        });
    </script>
@endpush
