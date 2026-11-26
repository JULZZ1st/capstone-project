        @if ($items->count())
            <x-inventory.table :items="$items" />
        @else
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-950">Inventory Items</h2>
                </div>
                <div class="p-8">
                    <x-dashboard.empty-state
                        icon="cube"
                        :message="request()->hasAny(['search', 'category', 'status'])
                            ? 'No inventory items match the current filters.'
                            : 'No inventory items have been recorded yet.'"
                    />
                    <p class="mt-1 text-center text-sm text-slate-400">
                        @if (request()->hasAny(['search', 'category', 'status']))
                            Try adjusting your search or filters.
                        @else
                            Start by adding a new inventory item.
                        @endif
                    </p>

                    @if (request()->hasAny(['search', 'category', 'status']))
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                            <a href="{{ route('inventory.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Clear filters</a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
