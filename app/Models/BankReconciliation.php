<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'date',
        'period_start',
        'period_end',
        'opening_balance',
        'bank_ending_balance',
        'cash_in',
        'cash_out',
        'calculated_bank_cash_in',
        'calculated_bank_cash_out',
        'financial_ledger_cash_in',
        'financial_ledger_cash_out',
        'closing_balance',
        'financial_ledger_ending_balance',
        'reconciliation_difference',
        'accounting_adjustment',
        'adjusted_bank_balance',
        'unresolved_discrepancy_amount',
        'status',
        'prepared_by', 'submitted_at', 'approved_by', 'approved_at', 'rejection_reason',
        'correction_requested_by', 'correction_requested_at', 'correction_count',
        'locked_by',
        'locked_at',
        'calculated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'decimal:2',
            'bank_ending_balance' => 'decimal:2',
            'cash_in' => 'decimal:2',
            'cash_out' => 'decimal:2',
            'calculated_bank_cash_in' => 'decimal:2',
            'calculated_bank_cash_out' => 'decimal:2',
            'financial_ledger_cash_in' => 'decimal:2',
            'financial_ledger_cash_out' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'financial_ledger_ending_balance' => 'decimal:2',
            'reconciliation_difference' => 'decimal:2',
            'accounting_adjustment' => 'decimal:2',
            'adjusted_bank_balance' => 'decimal:2',
            'unresolved_discrepancy_amount' => 'decimal:2',
            'locked_at' => 'datetime',
            'calculated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'correction_requested_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' || $this->locked_at !== null;
    }

    public function discrepancies()
    {
        return $this->hasMany(ReconciliationDiscrepancy::class);
    }

    public function preparer() { return $this->belongsTo(User::class, 'prepared_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isReadyForReview(): bool { return $this->status === 'ready_for_review'; }
    public function isApproved(): bool { return $this->status === 'approved_locked' || $this->isLocked(); }
}
