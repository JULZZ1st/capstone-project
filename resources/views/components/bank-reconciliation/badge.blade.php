@props(['status', 'variant' => 'ledger'])

@php
    $configKey = $variant === 'receipt' ? 'bank_reconciliation.receipt_statuses' : 'bank_reconciliation.statuses';
    $meta = config("{$configKey}.{$status}", ['label' => ucfirst((string) $status), 'class' => 'bg-slate-100 text-slate-800 ring-slate-200']);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset {$meta['class']}"]) }}>
    {{ $meta['label'] }}
</span>
