@props(['status'])

@php($meta = config("donations.statuses.{$status}", ['label' => ucfirst((string) $status), 'class' => 'bg-slate-100 text-slate-800 ring-slate-200']))

<span {{ $attributes->merge(['class' => "ui-status-badge {$meta['class']}"]) }}>
    {{ $meta['label'] }}
</span>
