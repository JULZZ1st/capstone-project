@props(['status'])

@php($meta = config("donations.statuses.{$status}", ['label' => ucfirst((string) $status), 'class' => 'bg-slate-100 text-slate-800 ring-slate-200']))

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {$meta['class']}"]) }}>
    {{ $meta['label'] }}
</span>
