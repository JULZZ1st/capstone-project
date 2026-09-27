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

<aside id="app-sidebar" class="border-b border-white/5 bg-gradient-to-b from-[#111c40] to-[#0a1024] text-white lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:overflow-hidden lg:border-b-0 transition-width">
    <style>
        /* Smooth width transition and label hiding when collapsed. Avoid display:none to keep transitions smooth. */
        .transition-width { transition: width 200ms ease; }
        #sidebar-toggle { position: relative; z-index: 50; }
        #sidebar-toggle svg { transition: transform 180ms ease; }

        .sidebar-label { display: inline-block; max-width: 1000px; overflow: hidden; vertical-align: middle; transition: opacity 180ms ease, max-width 180ms ease; }
        .sidebar-logo-text { display: inline-block; transition: opacity 180ms ease, max-width 180ms ease; overflow: hidden; }

        @media (min-width: 1024px) {
            /* keep sidebar viewport-pinned while preserving the absolute toggle positioning context */
            #app-sidebar { position: sticky; top: 0; }
            /* ensure toggle remains visible and clickable when sidebar is narrow */
            #sidebar-toggle { position: absolute; top: 0.6rem; right: 0.6rem; z-index: 60; }
            /* collapse to icon-only width */
            #app-sidebar.collapsed { width: 5rem; }
            #app-sidebar.collapsed .sidebar-label { opacity: 0; max-width: 0; }
            #app-sidebar.collapsed .sidebar-logo-text { opacity: 0; max-width: 0; }
            /* rotate the chevron when collapsed */
            #app-sidebar.collapsed #sidebar-toggle svg { transform: rotate(180deg); }
            #app-sidebar .nav-item { white-space: nowrap; }
        }
    </style>

    <div class="flex h-full flex-col">
        <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-inset ring-blue-400/20">
                <x-icon name="heart" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-white sidebar-logo-text">Donation Operations</p>
            </div>

            {{-- collapse/expand button on the right of the header --}}
            <button id="sidebar-toggle" type="button" title="Toggle sidebar" class="ml-auto rounded-md bg-white/5 p-1 text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/10">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Sidebar navigation">
            @foreach ($items as $item)
                @php($isActive = request()->routeIs($item['active']))
                <a
                    href="{{ route($item['route']) }}"
                    title="{{ $item['label'] }}"
                    class="nav-item group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $isActive ? 'bg-blue-600 text-white shadow-md shadow-blue-950/40' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
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

    <script>
        (function () {
            const sidebar = document.getElementById('app-sidebar');
            const storageKey = 'sidebarCollapsed';

            function applyState(collapsed) {
                if (!sidebar) return;
                if (collapsed) {
                    sidebar.classList.add('collapsed');
                    document.documentElement.classList.add('sidebar-collapsed');
                    sidebar.querySelector('#sidebar-toggle')?.setAttribute('aria-expanded','false');
                } else {
                    sidebar.classList.remove('collapsed');
                    document.documentElement.classList.remove('sidebar-collapsed');
                    sidebar.querySelector('#sidebar-toggle')?.setAttribute('aria-expanded','true');
                }
            }

            // Read initial state from localStorage (guarded)
            let collapsed = false;
            try {
                const raw = localStorage.getItem(storageKey);
                collapsed = raw === '1' || raw === 'true';
            } catch (e) {
                collapsed = false;
            }
            applyState(collapsed);

            // Use delegated click handler so listeners remain stable across navigations.
            document.addEventListener('click', function (e) {
                // robust closest for SVG and older browsers
                var node = e.target;
                while (node && node !== document) {
                    try {
                        if (node.matches && node.matches('#sidebar-toggle')) {
                            // found the toggle
                            collapsed = !collapsed;
                            try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (err) {}
                            applyState(collapsed);
                            return;
                        }
                    } catch (err) {
                        // ignore
                    }
                    node = node.parentNode;
                }
            });

            // Keep keyboard users informed
            document.addEventListener('keyup', function (e) {
                if (e.key === 'Escape' && document.documentElement.classList.contains('sidebar-collapsed')) {
                    // allow Esc to expand back
                    collapsed = false;
                    try { localStorage.setItem(storageKey, '0'); } catch (err) {}
                    applyState(collapsed);
                }
            });
        }());
    </script>
</aside>
