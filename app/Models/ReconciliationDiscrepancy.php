<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationDiscrepancy extends \Illuminate\Database\Eloquent\Model
{
    use Concerns\WorkspaceScoped;
    protected $fillable = [
        'workspace_id', 'bank_reconciliation_id', 'type', 'classification', 'amount', 'explanation',
        'evidence_reference', 'resolution_status', 'adjustment_amount', 'carry_forward',
        'resolved_by', 'resolved_at', 'cleared_bank_ledger_entry_id', 'cleared_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'carry_forward' => 'boolean', 'resolved_at' => 'datetime', 'cleared_at' => 'datetime'];
    }

    public function period(): BelongsTo { return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function clearedBankEntry(): BelongsTo { return $this->belongsTo(BankLedgerEntry::class, 'cleared_bank_ledger_entry_id'); }
}
