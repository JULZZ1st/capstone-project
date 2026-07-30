@props(['status'])

@php($statusConfig = config('inventory.stock_statuses.'.$status, ['label' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'bg-slate-100 text-slate-800 ring-slate-200']))

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$statusConfig['class']]) }}>
    {{ $statusConfig['label'] }}
</span>
