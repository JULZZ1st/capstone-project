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

    public const CARRY_FORWARD_ELIGIBLE_TYPES = ['outstanding_check', 'deposit_in_transit', 'cash_not_deposited'];

    public function typeLabel(): string
    {
        return ReconciliationDiscrepancyService::typeLabel($this->type, $this->custom_type);
    }

    /**
     * Whether this discrepancy still needs further action before the period can be
     * considered complete / submitted for Admin review. Mirrors, exactly, the rule
     * ReconciliationApprovalService::validateForApproval() already uses to decide
     * whether an unresolved timing difference blocks submission: a documented timing
     * item with a carry-forward-eligible type, a positive amount, an evidence
     * reference, and the carry-forward flag set does NOT block, even while its
     * resolution_status stays 'unresolved' (it will clear via matching or carry
     * forward on its own). This does not look at admin_review_status.
     */
    public function needsFurtherAction(): bool
    {
        if (in_array($this->resolution_status, ['resolved', 'accounted'], true)) {
            return false;
        }

        if ($this->isValidCarryForwardTiming()) {
            return false;
        }

        return true;
    }

    public function isValidCarryForwardTiming(): bool
    {
        return $this->classification === 'timing_difference'
            && in_array($this->type, self::CARRY_FORWARD_ELIGIBLE_TYPES, true)
            && (float) $this->amount > 0
            && filled($this->evidence_reference)
            && (bool) $this->carry_forward;
    }

    /**
     * A looser pre-submission gate used only by ReconciliationApprovalService::submit().
     * Staff may submit a draft period for Admin review even while needsFurtherAction()
     * is true, as long as Admin has already reviewed and accepted this discrepancy's
     * explanation -- Admin is about to review the whole period anyway. This never
     * changes what Step 3 displays (that still reads needsFurtherAction()/
     * remainingActionReason() directly), and final approval
     * (ReconciliationApprovalService::approve()) still enforces needsFurtherAction()
     * in full, so nothing here bypasses the locking safeguard.
     */
    public function blocksSubmission(): bool
    {
        if (! $this->needsFurtherAction()) {
            return false;
        }

        return $this->admin_review_status !== 'accepted';
    }

    /**
     * A short reason code for why this discrepancy still needs action, for display.
     * Returns null when no further action is required.
     */
    public function remainingActionReason(): ?string
    {
        if (! $this->needsFurtherAction()) {
            return null;
        }

        if ($this->admin_review_status === 'correction_requested') {
            return 'correction_required';
        }

        if ($this->admin_review_status !== 'accepted') {
            return 'pending_review';
        }

        if ($this->classification !== 'timing_difference' || ! in_array($this->type, self::CARRY_FORWARD_ELIGIBLE_TYPES, true)) {
            return 'ineligible_type';
        }

        if (blank($this->evidence_reference)) {
            return 'missing_evidence';
        }

        if (! $this->carry_forward) {
            return 'not_flagged_carry_forward';
        }

        return 'needs_resolution';
    }

    public function transactionDirection(): string
    {
        if ($this->bankLedgerEntry) {
            return $this->bankLedgerEntry->transaction_type === 'withdrawal' ? 'outgoing' : 'incoming';
        }

        $systemType = strtolower(class_basename((string) $this->system_record_type));
        if (in_array($systemType, ['cash_disbursement', 'cashdisbursement'], true)) {
            return 'outgoing';
        }
        if ($systemType === 'contribution') {
            return 'incoming';
        }

        return $this->type === 'outstanding_check' ? 'outgoing' : 'incoming';
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
