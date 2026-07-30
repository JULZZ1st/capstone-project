@props(['permissions', 'assignedPermissions'])

@php($assignedIds = $assignedPermissions->pluck('id')->all())

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @foreach ($permissions as $permission)
        <label class="flex items-start gap-2 text-[11px] text-slate-600">
            <input
                type="checkbox"
                class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                @checked(in_array($permission->id, $assignedIds, true))
                disabled
            >
            <span class="{{ in_array($permission->id, $assignedIds, true) ? 'font-semibold text-slate-950' : '' }}">
                {{ $permission->name }}
            </span>
        </label>
    @endforeach
</div>
