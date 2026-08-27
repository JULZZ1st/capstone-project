@php
    $items = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Donor Management', 'icon' => 'users', 'route' => 'donors.index', 'active' => 'donors.*'],
        ['label' => 'Donations Portal', 'icon' => 'gift', 'route' => 'donations.index', 'active' => 'donations.*'],
        ['label' => 'Fund Disbursements', 'icon' => 'banknotes', 'route' => 'disbursements.index', 'active' => 'disbursements.*'],
        ['label' => 'In-Kind Inventory', 'icon' => 'archive', 'route' => 'inventory.index', 'active' => 'inventory.*'],
        ['label' => 'Bank Reconciliation', 'icon' => 'scale', 'route' => 'bank-reconciliation.index', 'active' => 'bank-reconciliation.*'],
        ['label' => 'Report Generator', 'icon' => 'document', 'route' => 'reports.index', 'active' => 'reports.*'],
        ['label' => 'System Administration', 'icon' => 'shield-check', 'route' => 'system-security.index', 'active' => 'system-security.*'],
    ];
@endphp

<aside class="border-b border-white/5 bg-gradient-to-b from-[#111c40] to-[#0a1024] text-white lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:border-b-0">
    <div class="flex h-full flex-col">
        <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-inset ring-blue-400/20">
                <x-icon name="heart" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-white">Donation Operations</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($items as $item)
                @php($isActive = request()->routeIs($item['active']))
                <a
                    href="{{ route($item['route']) }}"
                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $isActive ? 'bg-blue-600 text-white shadow-md shadow-blue-950/40' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $isActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" />
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-3">
            @csrf
            <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-red-300/90 transition hover:bg-red-500/10 hover:text-red-200">
                <x-icon name="logout" class="h-5 w-5 shrink-0" />
                Logout
            </button>
        </form>
    </div>
</aside>
