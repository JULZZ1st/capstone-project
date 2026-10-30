@php
    $user = auth()->user();
    $items = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'visible' => $user?->can('viewAny', \App\Models\Donor::class)],
        ['label' => 'Donations Portal', 'icon' => 'gift', 'route' => 'donations.index', 'active' => 'donations.*', 'visible' => $user?->can('viewAny', \App\Models\Contribution::class)],
        ['label' => 'Fund Requests', 'icon' => 'banknotes', 'route' => 'disbursements.index', 'active' => 'disbursements.*', 'visible' => $user?->can('viewAny', \App\Models\CashDisbursement::class)],
        ['label' => 'In-Kind Inventory', 'icon' => 'archive', 'route' => 'inventory.index', 'active' => 'inventory.*', 'visible' => $user?->can('viewAny', \App\Models\InventoryItem::class)],
        ['label' => 'Bank Reconciliation', 'icon' => 'scale', 'route' => 'bank-reconciliation.index', 'active' => 'bank-reconciliation.*', 'visible' => $user?->can('viewAny', \App\Models\BankLedgerEntry::class)],
        ['label' => 'Report Generator', 'icon' => 'document', 'route' => 'reports.index', 'active' => 'reports.*', 'visible' => $user?->can('viewAny', \App\Models\Donor::class)],
        ['label' => 'System Administration', 'icon' => 'shield-check', 'route' => 'system-security.index', 'active' => 'system-security.*', 'visible' => $user?->can('updateSecuritySettings', \App\Models\SecurityActivityEvent::class)],
    ];
    $items = array_values(array_filter($items, fn (array $item): bool => (bool) ($item['visible'] ?? true)));
@endphp

<aside id="app-sidebar" class="border-b border-slate-700/60 bg-gradient-to-b from-[#0f1d46] via-[#0f1c3a] to-[#0a1024] text-white shadow-[0_20px_45px_rgba(15,23,42,0.28)] lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:overflow-hidden lg:border-b-0 transition-width">
    <style>
        /* Smooth width transition and label hiding when collapsed. Avoid display:none to keep transitions smooth. */
        .transition-width { transition: width 200ms ease; }
        #sidebar-toggle { position: relative; z-index: 50; }
        #sidebar-toggle svg { transition: transform 180ms ease; }

        .sidebar-label { display: inline-block; max-width: 1000px; overflow: hidden; vertical-align: middle; transition: opacity 180ms ease, max-width 180ms ease; }
        .sidebar-logo-text { display: inline-block; transition: opacity 180ms ease, max-width 180ms ease; overflow: hidden; }

        @media (min-width: 1024px) {
            #app-sidebar { position: sticky; top: 0; }
            #sidebar-toggle { position: absolute; top: 0.7rem; right: 0.7rem; z-index: 60; }
            #app-sidebar.collapsed { width: 5.25rem; }
            #app-sidebar.collapsed .sidebar-label { opacity: 0; max-width: 0; }
            #app-sidebar.collapsed .sidebar-logo-text { opacity: 0; max-width: 0; }
            #app-sidebar.collapsed #sidebar-toggle svg { transform: rotate(180deg); }
            #app-sidebar .nav-item { white-space: nowrap; }
        }
        @media (max-width: 1023px) {
            #app-sidebar #sidebar-menu { display: none; }
            #app-sidebar.mobile-open #sidebar-menu { display: flex; max-height: calc(100dvh - 5rem); overflow-y: auto; }
            #app-sidebar.mobile-open #sidebar-toggle svg { transform: rotate(180deg); }
        }
    </style>

    <noscript><style>@media (max-width: 1023px) { #app-sidebar #sidebar-menu { display: flex; } }</style></noscript>

    <div class="flex h-full flex-col">
        <div class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-indigo-500 text-white shadow-lg shadow-blue-500/20 ring-1 ring-inset ring-white/15">
                <x-icon name="heart" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-base font-semibold tracking-tight text-slate-100 sidebar-logo-text">Donation Operations</p>
            </div>

            <button id="sidebar-toggle" type="button" aria-label="Toggle navigation" title="Toggle navigation" aria-controls="sidebar-menu" aria-expanded="false" class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 bg-slate-800/70 text-slate-200 shadow-sm transition hover:border-sky-300/40 hover:bg-slate-700/80 hover:text-white focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </button>
        </div>

        <div id="sidebar-menu" class="flex min-h-0 flex-1 flex-col">
        <nav class="flex-1 space-y-1.5 overflow-y-auto px-3 py-4" aria-label="Sidebar navigation">
            @foreach ($items as $item)
                @php($isActive = request()->routeIs($item['active']))
                <a
                    href="{{ route($item['route']) }}"
                    title="{{ $item['label'] }}"
                    class="nav-item group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 ease-out {{ $isActive ? 'bg-gradient-to-r from-blue-500 to-indigo-500 text-white shadow-[0_10px_25px_rgba(59,130,246,0.28)] ring-1 ring-inset ring-blue-300/40' : 'text-slate-300 hover:bg-white/6 hover:text-white' }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $isActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" />
                    <span class="truncate sidebar-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button title="Logout" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-red-300/90 transition hover:bg-red-500/10 hover:text-red-200">
                    <x-icon name="logout" class="h-5 w-5 shrink-0" />
                    <span class="sidebar-label">Logout</span>
                </button>
            </form>
        </div>
        </div>
    </div>

</aside>
