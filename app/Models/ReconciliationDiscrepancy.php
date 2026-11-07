<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\BankReconciliation\ReconciliationDiscrepancyService;

class ReconciliationDiscrepancy extends \Illuminate\Database\Eloquent\Model
{
    use Concerns\WorkspaceScoped;
    protected $fillable = [
        'workspace_id', 'bank_reconciliation_id', 'reported_by', 'type', 'custom_type', 'classification', 'amount', 'explanation',
        'evidence_reference', 'evidence_path', 'resolution_explanation', 'resolution_evidence_path', 'bank_ledger_entry_id', 'system_record_type', 'system_record_id',
        'resolution_status', 'admin_review_status', 'admin_reviewed_by', 'admin_reviewed_at', 'admin_review_note',
        'adjustment_amount', 'carry_forward',
        'resolved_by', 'resolved_at', 'cleared_bank_ledger_entry_id', 'cleared_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'carry_forward' => 'boolean', 'resolved_at' => 'datetime', 'cleared_at' => 'datetime', 'admin_reviewed_at' => 'datetime'];
    }

    public function typeLabel(): string
    {
        return ReconciliationDiscrepancyService::typeLabel($this->type, $this->custom_type);
    }

    public function period(): BelongsTo { return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id'); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function adminReviewer(): BelongsTo { return $this->belongsTo(User::class, 'admin_reviewed_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function clearedBankEntry(): BelongsTo { return $this->belongsTo(BankLedgerEntry::class, 'cleared_bank_ledger_entry_id'); }
    public function bankLedgerEntry(): BelongsTo { return $this->belongsTo(BankLedgerEntry::class); }
    public function systemRecord()
    {
        return $this->morphTo(__FUNCTION__, 'system_record_type', 'system_record_id');
    }
}
