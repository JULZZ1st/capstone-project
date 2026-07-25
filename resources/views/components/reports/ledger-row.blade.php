@props(['row'])

<div class="flex items-center justify-between border-b border-slate-100 py-4 last:border-b-0">
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-slate-950">{{ $row['donor_name'] }}</p>
        <p class="truncate text-xs text-slate-500">{{ $row['campaign_name'] ?? 'General Fund' }} &middot; Method: {{ $row['payment_method_label'] }}</p>
    </div>
    <div class="flex-none text-right">
        <p class="text-sm font-bold text-slate-950">₱{{ number_format($row['amount'], 0) }}</p>
        <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $row['status_class'] }}">
            {{ $row['status_label'] }}
        </span>
    </div>
</div>
