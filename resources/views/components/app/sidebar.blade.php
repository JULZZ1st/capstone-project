@php
    $items = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Donor Management', 'route' => 'donors.index', 'active' => 'donors.*'],
        ['label' => 'Donations Portal', 'route' => 'donations.index', 'active' => 'donations.*'],
        ['label' => 'In-Kind Inventory', 'route' => 'inventory.index', 'active' => 'inventory.*'],
        ['label' => 'Bank Reconciliation', 'route' => 'bank-reconciliation.index', 'active' => 'bank-reconciliation.*'],
        ['label' => 'Financial Ledger', 'route' => 'workflows.index', 'active' => 'cash.*'],
        ['label' => 'Report Generator', 'route' => 'reports.index', 'active' => 'reports.*'],
        ['label' => 'System Administration', 'route' => 'system-security.index', 'active' => 'system-security.*'],
    ];
@endphp

<aside class="border-b border-slate-200 bg-slate-950 text-white lg:sticky lg:top-0 lg:h-screen lg:w-72 lg:border-b-0">
    <div class="flex h-full flex-col">
        <div class="border-b border-white/10 px-5 py-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-green-300">Donation Operations</p>
            <p class="mt-2 text-lg font-bold">{{ config('dashboard.barangay_name') }}</p>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4">
            @foreach ($items as $item)
                @if (isset($item['route']))
                    <a
                        href="{{ route($item['route']) }}"
                        class="block rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($item['active']) ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}"
                    >
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="block rounded-md px-3 py-2 text-sm font-medium text-slate-500">
                        {{ $item['label'] }}
                    </span>
                @endif
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-3">
            @csrf
            <button class="w-full rounded-md px-3 py-2 text-left text-sm font-medium text-red-200 hover:bg-red-500/10 hover:text-red-100">
                Logout
            </button>
        </form>
    </div>
</aside>
