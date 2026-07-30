<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use Illuminate\Support\Collection;

/**
 * Scores logged contributions against a bank ledger entry so treasury staff can be
 * shown likely matches instead of reconciling every row by hand. Purely computed
 * (nothing here is persisted) so it stays cheap to re-run and easy to extend with
 * new signals later.
 */
class LedgerMatchFinder
{
    /**
     * @param  Collection<int, Contribution>  $candidates
     * @return Collection<int, array{contribution: Contribution, score: int, reasons: array<int, string>}>
     */
    public function suggest(BankLedgerEntry $entry, Collection $candidates): Collection
    {
        $config = config('bank_reconciliation.matching');

        return $candidates
            ->map(fn (Contribution $contribution) => $this->score($entry, $contribution, $config))
            ->filter(fn (array $result) => $result['score'] >= $config['minimum_score'])
            ->sortByDesc('score')
            ->take($config['max_suggestions'])
            ->values();
    }

    private function score(BankLedgerEntry $entry, Contribution $contribution, array $config): array
    {
        $score = 0;
        $reasons = [];
        $weights = $config['weights'];

        if ($this->referenceMatches($entry->reference_number, $contribution->reference_number)) {
            $score += $weights['reference_number'];
            $reasons[] = 'Reference number matches';
        }

        if ($this->amountMatches($entry->amount, $contribution->amount)) {
            $score += $weights['amount'];
            $reasons[] = 'Amount matches';
        }

        if ($entry->payment_method && $entry->payment_method === $contribution->payment_method) {
            $score += $weights['payment_method'];
            $reasons[] = 'Payment method matches';
        }

        if ($this->withinDateWindow($entry->transaction_date, $contribution->received_at, $config['date_window_days'])) {
            $score += $weights['date_proximity'];
            $reasons[] = 'Transaction date is close to the donation date';
        }

        return [
            'contribution' => $contribution,
            'score' => $score,
            'reasons' => $reasons,
        ];
    }

    private function referenceMatches(?string $entryReference, ?string $contributionReference): bool
    {
        if (! $entryReference || ! $contributionReference) {
            return false;
        }

        return $this->normalizeReference($entryReference) === $this->normalizeReference($contributionReference);
    }

    private function normalizeReference(string $reference): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $reference));
    }

    private function amountMatches(mixed $entryAmount, mixed $contributionAmount): bool
    {
        if ($entryAmount === null || $contributionAmount === null) {
            return false;
        }

        return bccomp((string) $entryAmount, (string) $contributionAmount, 2) === 0;
    }

    private function withinDateWindow(mixed $transactionDate, mixed $receivedAt, int $windowDays): bool
    {
        if (! $transactionDate || ! $receivedAt) {
            return false;
        }

        return abs($transactionDate->diffInDays($receivedAt)) <= $windowDays;
    }
}
