<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankLedgerEntry extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'bank_account',
        'statement_id',
        'duplicate_fingerprint',
        'description',
        'reference_number',
        'amount',
        'transaction_date',
        'transaction_type',
        'payment_method',
        'status',
        'matched_contribution_id',
        'matched_by',
        'matched_at',
        'reconciled_by',
        'reconciled_at',
        'notes',
        'discrepancy_amount',
        'discrepancy_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'matched_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'discrepancy_amount' => 'decimal:2',
        ];
    }

    public function matchedContribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class, 'matched_contribution_id');
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function withdrawalDisbursementMatches(): HasMany
    {
        return $this->hasMany(BankWithdrawalDisbursementMatch::class);
    }

    public function matchedDisbursements(): BelongsToMany
    {
        return $this->belongsToMany(
            CashDisbursement::class,
            'bank_withdrawal_disbursement_matches',
            'bank_ledger_entry_id',
            'cash_disbursement_id',
        )->withTimestamps();
    }

    public function clearedDiscrepancy(): HasOne
    {
        return $this->hasOne(ReconciliationDiscrepancy::class, 'cleared_bank_ledger_entry_id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReconciliationDiscrepancy::class);
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('status', '!=', 'reconciled');
    }

    public function scopeIncoming($query)
    {
        // Older manually-created rows may predate transaction_type; treat null as
        // the historical default deposit instead of silently hiding them.
        return $query->where(function ($query) {
            $query->where('transaction_type', 'deposit')->orWhereNull('transaction_type');
        });
    }

    public function scopeWithdrawalsToReview($query)
    {
        return $query
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'unreconciled');
    }

    public function scopeForBankAccount($query, ?string $bankAccount)
    {
        $primary = config('bank_reconciliation.bank_accounts.0');

        return $query->where(function ($query) use ($bankAccount, $primary) {
            $query->where('bank_account', $bankAccount);
            if ($bankAccount === $primary) {
                $query->orWhereNull('bank_account');
            }
        });
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    /**
     * Whether this bank movement blocks SUBMISSION because it is still
     * unmatched/unreconciled and has no discrepancy report that is itself
     * submission-safe. Mirrors ReconciliationDiscrepancy::blocksSubmission() so the two
     * checks can never disagree: a bank row documented by a discrepancy that Admin has
     * accepted (or a self-certifying, evidence-backed carry-forward timing item) doesn't
     * need to wait for literal matching before Staff can submit the period. See
     * blocksApproval() for the stricter rule final approval uses.
     */
    public function blocksSubmission(): bool
    {
        if (! in_array($this->status, ['unmatched', 'unreconciled'], true)) {
            return false;
        }

        $report = $this->discrepancies->sortByDesc('id')->first();

        return ! $report || $report->blocksSubmission();
    }

    /**
     * Whether this bank movement blocks FINAL APPROVAL. Resolving a discrepancy (Staff's
     * "Resolve Discrepancy" action, or the auto-resolve-on-accept path for
     * accounting_adjustment discrepancies) never flips this row's own `status` column --
     * only an actual board match, a withdrawal finalize, or ReconciliationPeriodService's
     * cross-period clearTimingItems() does that. Without this check, a bank row whose
     * discrepancy is already fully resolved/accounted, or validly documented as a
     * carry-forward timing item, would block approval forever even though
     * ReconciliationDiscrepancy::needsFurtherAction() already considers it done -- the
     * same contradiction test_documented_timing_item_does_not_block_independent_approval
     * was meant to rule out, just never exercised with a real tied bank_ledger_entry_id.
     * Mirrors blocksSubmission() but uses the strict needsFurtherAction() bar instead of
     * the looser blocksSubmission() one, since final approval must not be bypassed by a
     * merely Admin-accepted-but-still-undocumented explanation.
     */
    public function blocksApproval(): bool
    {
        if (! in_array($this->status, ['unmatched', 'unreconciled'], true)) {
            return false;
        }

        $report = $this->discrepancies->sortByDesc('id')->first();

        return ! $report || $report->needsFurtherAction();
    }

    public function isWithdrawal(): bool
    {
        return $this->transaction_type === 'withdrawal';
    }
}
