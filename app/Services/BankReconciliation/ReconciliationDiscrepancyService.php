<?php

namespace App\Services\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\ReconciliationDiscrepancy;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationDiscrepancyService
{
    public const TYPES = ['unmatched_amount', 'bank_charge', 'interest', 'outstanding_check', 'deposit_in_transit', 'cash_not_deposited', 'recording_error', 'unidentified_transaction', 'other'];
    public const CLASSIFICATIONS = ['accounting_adjustment', 'timing_difference'];
    public const STATUSES = ['unresolved', 'resolved', 'accounted'];

    public static function allowedTypesForContext(string $context): array
    {
        return match ($context) {
            'incoming' => ['deposit_in_transit', 'cash_not_deposited', 'recording_error', 'unidentified_transaction', 'other'],
            'outgoing' => ['bank_charge', 'outstanding_check', 'recording_error', 'unidentified_transaction', 'other'],
            'system' => ['deposit_in_transit', 'cash_not_deposited', 'outstanding_check', 'recording_error', 'other'],
            default => self::TYPES,
        };
    }

    public static function typeLabel(?string $type, ?string $customType = null): string
    {
        if ($type === 'other') {
            return filled($customType) ? $customType : 'Others';
        }

        return str_replace('_', ' ', (string) $type);
    }

    public function create(BankReconciliation $period, array $data, User $user): ReconciliationDiscrepancy
    {
        if ($period->isLocked()) {
            throw ValidationException::withMessages(['period' => 'Locked reconciliation periods cannot be changed.']);
        }

        return DB::transaction(function () use ($period, $data, $user) {
            $duplicate = $period->discrepancies()
                ->where('resolution_status', 'unresolved')
                ->when($data['bank_ledger_entry_id'] ?? null, fn ($query, $id) => $query->where('bank_ledger_entry_id', $id))
                ->when(($data['system_record_type'] ?? null) && ($data['system_record_id'] ?? null), fn ($query) => $query->where('system_record_type', $data['system_record_type'])->where('system_record_id', $data['system_record_id']))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['source' => 'An unresolved discrepancy already exists for this record in the selected period. Update that report instead.']);
            }
            $discrepancy = $period->discrepancies()->create([
                ...$data,
                'resolved_by' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? $user->id : null,
                'resolved_at' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? now() : null,
            ]);
            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'bank_reconciliation_discrepancy_created',
                'summary' => "{$user->name} recorded a ".self::typeLabel($discrepancy->type, $discrepancy->custom_type)." discrepancy for reconciliation period #{$period->id}.",
                'metadata' => [
                    'bank_reconciliation_id' => $period->id,
                    'reconciliation_discrepancy_id' => $discrepancy->id,
                    'type' => $discrepancy->type,
                    'custom_type' => $discrepancy->custom_type,
                    'amount' => $discrepancy->amount,
                    'evidence_reference' => $discrepancy->evidence_reference,
                ],
                'occurred_at' => now(),
            ]);
            app(ReconciliationPeriodService::class)->recalculate($period->fresh());
            return $discrepancy;
        });
    }

    public function update(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, array $data, User $user): ReconciliationDiscrepancy
    {
        if ($period->isLocked() || $discrepancy->bank_reconciliation_id !== $period->id || $discrepancy->resolution_status !== 'unresolved') {
            throw ValidationException::withMessages(['discrepancy' => 'Only unresolved discrepancies in an editable period can be updated.']);
        }
        return DB::transaction(function () use ($period, $discrepancy, $data, $user) {
            $discrepancy->update([
                ...$data,
                'resolved_by' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? $user->id : null,
                'resolved_at' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? now() : null,
            ]);
            SecurityActivityEvent::query()->create(['actor_id' => $user->id, 'action' => 'bank_reconciliation_discrepancy_updated', 'summary' => "{$user->name} updated discrepancy #{$discrepancy->id}.", 'metadata' => ['bank_reconciliation_id' => $period->id, 'reconciliation_discrepancy_id' => $discrepancy->id, 'type' => $discrepancy->type, 'custom_type' => $discrepancy->custom_type], 'occurred_at' => now()]);
            app(ReconciliationPeriodService::class)->recalculate($period->fresh());
            return $discrepancy->fresh();
        });
    }

    public function review(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, string $decision, ?string $note, User $user): ReconciliationDiscrepancy
    {
        if ($period->isLocked() || (int) $discrepancy->bank_reconciliation_id !== (int) $period->id) {
            throw ValidationException::withMessages(['discrepancy' => 'Discrepancies in locked periods cannot be reviewed.']);
        }

        if (! in_array($decision, ['accepted', 'correction_requested'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose a valid discrepancy review decision.']);
        }

        return DB::transaction(function () use ($period, $discrepancy, $decision, $note, $user) {
            $discrepancy->update([
                'admin_review_status' => $decision,
                'admin_reviewed_by' => $user->id,
                'admin_reviewed_at' => now(),
                'admin_review_note' => filled($note) ? trim($note) : null,
            ]);

            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'bank_reconciliation_discrepancy_reviewed',
                'summary' => "{$user->name} ".($decision === 'accepted' ? 'accepted the explanation for' : 'requested correction for')." discrepancy #{$discrepancy->id}.",
                'metadata' => [
                    'bank_reconciliation_id' => $period->id,
                    'reconciliation_discrepancy_id' => $discrepancy->id,
                    'decision' => $decision,
                    'note' => $note,
                ],
                'occurred_at' => now(),
            ]);

            return $discrepancy->fresh(['reporter', 'adminReviewer']);
        });
    }

    public function resolve(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, string $explanation, ?string $evidencePath, User $user): ReconciliationDiscrepancy
    {
        if ($period->isLocked() || (int) $discrepancy->bank_reconciliation_id !== (int) $period->id) {
            throw ValidationException::withMessages(['discrepancy' => 'Discrepancies in locked periods cannot be resolved.']);
        }
        if ($discrepancy->admin_review_status !== 'accepted' || $discrepancy->resolution_status !== 'unresolved') {
            throw ValidationException::withMessages(['discrepancy' => 'Only unresolved discrepancies accepted by Admin can be resolved.']);
        }

        return DB::transaction(function () use ($period, $discrepancy, $explanation, $evidencePath, $user) {
            $resolvedAt = now();
            $discrepancy->update([
                'resolution_status' => 'resolved',
                'resolution_explanation' => trim($explanation),
                'resolution_evidence_path' => $evidencePath,
                'resolved_by' => $user->id,
                'resolved_at' => $resolvedAt,
            ]);
            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'bank_reconciliation_discrepancy_resolved',
                'summary' => "{$user->name} resolved discrepancy #{$discrepancy->id} after Admin acceptance.",
                'metadata' => [
                    'bank_reconciliation_id' => $period->id,
                    'reconciliation_discrepancy_id' => $discrepancy->id,
                    'resolution_explanation' => trim($explanation),
                    'resolution_evidence_path' => $evidencePath,
                    'resolved_by' => $user->id,
                    'resolved_at' => $resolvedAt->toIso8601String(),
                ],
                'occurred_at' => $resolvedAt,
            ]);
            app(ReconciliationPeriodService::class)->recalculate($period->fresh());
            return $discrepancy->fresh(['reporter', 'adminReviewer', 'resolver']);
        });
    }
}
