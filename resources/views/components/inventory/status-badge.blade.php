@props(['status'])

@php($statusConfig = config('inventory.stock_statuses.'.$status, ['label' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'bg-slate-100 text-slate-800 ring-slate-200']))

<span {{ $attributes->merge(['class' => 'ui-status-badge '.$statusConfig['class']]) }}>
    {{ $statusConfig['label'] }}
</span>
