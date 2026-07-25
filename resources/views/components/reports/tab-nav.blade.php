@props(['active', 'year', 'month'])

@php
    $tabs = [
        'financial-ledgers' => 'Financial Ledgers',
        'in-kind-audits' => 'In-Kind Audits',
        'philanthropy-roster' => 'Philanthropy Roster',
    ];
@endphp

<div class="grid gap-3 sm:grid-cols-3">
    @foreach ($tabs as $key => $label)
        <a
            href="{{ route('reports.index', ['tab' => $key, 'year' => $year, 'month' => $month]) }}"
            class="flex items-center justify-center rounded-lg border px-4 py-3 text-sm font-semibold transition {{ $active === $key ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
        >
            {{ $label }}
        </a>
    @endforeach
</div>
