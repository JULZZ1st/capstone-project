@props(['status', 'variant' => 'ledger'])

@php
    $configKey = $variant === 'receipt' ? 'bank_reconciliation.receipt_statuses' : 'bank_reconciliation.statuses';
    $meta = config("{$configKey}.{$status}", ['label' => ucfirst((string) $status), 'class' => 'bg-slate-100 text-slate-800 ring-slate-200']);
@endphp

<span {{ $attributes->merge(['class' => "ui-status-badge {$meta['class']}"]) }}>
    {{ $meta['label'] }}
</span>
