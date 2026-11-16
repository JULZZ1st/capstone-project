<dialog id="new-bank-period-modal" class="m-auto w-[min(44rem,calc(100vw-1.5rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60" aria-labelledby="new-bank-period-title">
    <form method="POST" action="{{ route('bank-reconciliation.periods.store') }}" class="flex max-h-[90vh] flex-col overflow-hidden bg-white">
        @csrf
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Reconciliation period</p>
                <h2 id="new-bank-period-title" class="mt-1 text-lg font-bold text-slate-950">New Reconciliation</h2>
                <p class="mt-1 text-sm text-slate-500">Choose the statement month, bank account, and starting balance.</p>
            </div>
            <button type="button" data-dialog-close="new-bank-period-modal" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close new reconciliation dialog"><x-icon name="x-circle" class="h-5 w-5" /></button>
        </header>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
            @if($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">Please review the highlighted reconciliation details.</div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-form.label for="reconciliation-month">Month</x-form.label>
                    <select id="reconciliation-month" name="period_month" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select month</option>
                        @for($month = 1; $month <= 12; $month++)
                            <option value="{{ $month }}" @selected((int) old('period_month', now()->month) === $month)>{{ now()->setMonth($month)->format('F') }}</option>
                        @endfor
                    </select>
                    <x-form.error name="period_month" />
                </div>
                <div>
                    <x-form.label for="reconciliation-year">Year</x-form.label>
                    <select id="reconciliation-year" name="period_year" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @for($year = now()->year - 1; $year <= now()->year + 1; $year++)
                            <option value="{{ $year }}" @selected((int) old('period_year', now()->year) === $year)>{{ $year }}</option>
                        @endfor
                    </select>
                    <x-form.error name="period_year" />
                </div>
            </div>

            <div>
                <x-form.label for="reconciliation-bank-account">Bank account</x-form.label>
                <select id="reconciliation-bank-account" name="bank_account" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($bankAccounts as $account)
                        <option value="{{ $account }}" @selected(old('bank_account') === $account)>{{ $account }}</option>
                    @endforeach
                </select>
                <x-form.error name="bank_account" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-form.label for="reconciliation-opening-balance">Starting bank balance</x-form.label>
                    <div class="relative mt-1.5"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-500">₱</span><input id="reconciliation-opening-balance" name="opening_balance" type="number" min="0" step="0.01" required value="{{ old('opening_balance') }}" class="block h-11 w-full rounded-lg border-slate-300 pl-8 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></div>
                    <x-form.error name="opening_balance" />
                </div>
                <div>
                    <x-form.label for="reconciliation-ending-balance">Ending bank balance <span class="font-normal text-slate-400">(optional)</span></x-form.label>
                    <div class="relative mt-1.5"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-500">₱</span><input id="reconciliation-ending-balance" name="bank_ending_balance" type="number" min="0" step="0.01" value="{{ old('bank_ending_balance') }}" class="block h-11 w-full rounded-lg border-slate-300 pl-8 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></div>
                    <x-form.error name="bank_ending_balance" />
                </div>
            </div>

            <div>
                <x-form.label for="reconciliation-opening-explanation">Starting balance source</x-form.label>
                <textarea id="reconciliation-opening-explanation" name="opening_balance_explanation" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Explain where the starting balance was verified when this is the first confirmed period.">{{ old('opening_balance_explanation') }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Required when this account has no previously confirmed reconciliation period.</p>
                <x-form.error name="opening_balance_explanation" />
                <x-form.error name="period_start" />
            </div>
        </div>

        <footer class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-6">
            <button type="button" data-dialog-close="new-bank-period-modal" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Create Reconciliation</button>
        </footer>
    </form>
</dialog>

@if($errors->any())
    <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('new-bank-period-modal')?.showModal());</script>
@endif
