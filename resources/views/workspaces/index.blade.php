@extends('layouts.app')

@section('title', 'Workspace Management | '.config('dashboard.barangay_name'))
@section('page-title', 'Workspace Management')
@section('page-subtitle', 'Create isolated workspaces and assign one Admin to each workspace.')

@section('content')
    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-semibold text-slate-900">Workspaces and assigned Admins</h2><p class="text-xs text-slate-500">Platform-level controls. Operational records still require an authorized active workspace.</p></div>
                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $workspaces->count() }} workspaces</span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($workspaces as $workspace)
                    <div class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-slate-900">{{ $workspace->name }}</span><span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ $workspace->isActive() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ ucfirst($workspace->status) }}</span></div>
                            <p class="mt-1 text-xs text-slate-500">{{ $workspace->slug }} · {{ $workspace->active_member_count }} active member(s)</p>
                            <p class="mt-1 text-sm text-slate-700">Admin: {{ $workspace->memberships->first(fn ($membership) => $membership->role?->name === 'admin')?->user?->email ?? 'Not assigned' }}</p>
                        </div>
                        @if ($workspace->slug !== 'legacy')
                            <form method="POST" action="{{ route('workspaces.toggle', $workspace) }}">
                                @csrf @method('PATCH')
                                <button class="rounded border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ $workspace->isActive() ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Create Workspace and Admin</h2><p class="text-xs text-slate-500">The Admin receives a temporary password and must change it at first login.</p></div>
            <form method="POST" action="{{ route('workspaces.store') }}" class="grid gap-4 p-5 md:grid-cols-2">
                @csrf
                @foreach ([['name','Workspace name','text'],['slug','Workspace slug (optional)','text'],['admin_first_name','Admin first name','text'],['admin_last_name','Admin last name','text'],['admin_email','Admin email','email'],['admin_password','Temporary password','password'],['admin_password_confirmation','Confirm password','password']] as [$field,$label,$type])
                    <div><label for="{{ $field }}" class="block text-xs font-semibold text-slate-600">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" class="mt-1 w-full rounded border-slate-300 text-sm" @unless($field === 'slug') required @endunless>@error($field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                @endforeach
                <div class="md:col-span-2 flex justify-end"><button class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create workspace</button></div>
            </form>
        </section>
    </div>
@endsection
