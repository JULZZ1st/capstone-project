@props(['actions', 'selected' => null])

<select
    name="action"
    class="h-9 rounded-md border-slate-300 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
>
    <option value="">All Actions</option>
    @foreach ($actions as $value => $label)
        <option value="{{ $value }}" @selected($selected === $value)>{{ $label }}</option>
    @endforeach
</select>
