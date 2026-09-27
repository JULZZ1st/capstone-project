<?php

namespace App\Services\Reports;

use App\Models\Donor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DonorGivingSummaryReportBuilder
{
    /**
     * Build donor giving summary rows based on filters and sorting.
     *
     * @param  array{year?: int|null, month?: int|null, search?: string|null, sort?: string|null}  $filters
     * @return array{rows: Collection, total_donors: int, filters: array}
     */
    public function build(array $filters = []): array
    {
        $year = $filters['year'] ?? null;
        $month = $filters['month'] ?? null;
        $search = $filters['search'] ?? null;
        $sort = $filters['sort'] ?? null;

        $scopeToPeriod = function ($query) use ($year, $month) {
            $query->where('status', '!=', 'cancelled')->whereNull('voided_at');

            if ($year) {
                $query->whereYear('received_at', $year);
            }

            if ($month) {
                $query->whereMonth('received_at', $month);
            }

            return $query;
        };

        $donors = Donor::query()
            ->search($search)
            ->withCount(['contributions as total_donation_count' => $scopeToPeriod])
            ->withSum(['contributions as total_cash_donations' => fn ($q) => $scopeToPeriod($q)->where('type', 'cash')], 'amount')
            ->withMin(['contributions as first_donation_date' => $scopeToPeriod], 'received_at')
            ->withMax(['contributions as last_donation_date' => $scopeToPeriod], 'received_at')
            ->whereHas('contributions', $scopeToPeriod)
            ->get();

        $inKindTotalsByDonor = DB::table('contribution_items')
            ->join('contributions', 'contributions.id', '=', 'contribution_items.contribution_id')
            ->whereIn('contributions.donor_id', $donors->pluck('id'))
            ->where('contributions.status', '!=', 'cancelled')
            ->whereNull('contributions.voided_at')
            ->when($year, fn ($q) => $q->whereYear('contributions.received_at', $year))
            ->when($month, fn ($q) => $q->whereMonth('contributions.received_at', $month))
            ->groupBy('contributions.donor_id')
            ->select('contributions.donor_id', DB::raw('COALESCE(SUM(contribution_items.estimated_value), 0) as total'))
            ->pluck('total', 'donor_id');

        $rows = $donors->map(function (Donor $donor) use ($inKindTotalsByDonor) {
            $cash = (float) ($donor->total_cash_donations ?? 0);
            $inKind = (float) ($inKindTotalsByDonor[$donor->id] ?? 0);

            return [
                'donor_id' => $donor->id,
                'donor_name' => $donor->full_name,
                'email' => $donor->email,
                'phone' => $donor->contact_number,
                'total_donation_count' => (int) ($donor->total_donation_count ?? 0),
                'total_cash_donations' => $cash,
                'total_in_kind_donations' => $inKind,
                'combined_donation_value' => $cash + $inKind,
                'first_donation_date' => $this->formatDate($donor->first_donation_date),
                'last_donation_date' => $this->formatDate($donor->last_donation_date),
            ];
        });

        $rows = (match ($sort) {
            'highest' => $rows->sortByDesc('combined_donation_value'),
            'lowest' => $rows->sortBy('combined_donation_value'),
            'most_contributions' => $rows->sortByDesc('total_donation_count'),
            'latest' => $rows->sortByDesc('last_donation_date'),
            default => $rows->sortByDesc('combined_donation_value'),
        })->values();

        return [
            'rows' => $rows,
            'total_donors' => $rows->count(),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'search' => $search,
                'sort' => $sort,
            ],
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }
}

