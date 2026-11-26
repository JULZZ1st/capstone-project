@props(['label', 'tone' => 'slate'])

@php
    $classes = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
    ][$tone] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
@endphp

<span {{ $attributes->merge(['class' => "ui-status-badge {$classes}"]) }}>
    {{ $label }}
</span>
