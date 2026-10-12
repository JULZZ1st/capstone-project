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

    protected $fillable = [
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

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public function isWithdrawal(): bool
    {
        return $this->transaction_type === 'withdrawal';
    }
}
