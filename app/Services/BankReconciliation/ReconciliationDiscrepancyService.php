<?php

namespace App\Services\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\ReconciliationDiscrepancy;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function create(BankReconciliation $period, array $data, User $user, ?UploadedFile $evidence = null): ReconciliationDiscrepancy
    {
        return $this->withEvidence($evidence, 'bank-reconciliation/discrepancies/'.$period->id, 'supporting_evidence', function ($path) use ($period, $data, $user) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            if ($period->isLocked()) {
                throw ValidationException::withMessages(['period' => 'Locked reconciliation periods cannot be changed.']);
            }
            if ($path !== null) {
                $data['evidence_path'] = $path;
            }
            $this->validateSourceRecord($period, $data);

            $duplicate = false;
            if ($data['bank_ledger_entry_id'] ?? null) {
                $duplicate = $period->discrepancies()
                    ->where('resolution_status', 'unresolved')
                    ->where('bank_ledger_entry_id', $data['bank_ledger_entry_id'])
                    ->exists();
            } elseif (($data['system_record_type'] ?? null) && ($data['system_record_id'] ?? null)) {
                $duplicate = $period->discrepancies()
                    ->where('resolution_status', 'unresolved')
                    ->where('system_record_type', $data['system_record_type'])
                    ->where('system_record_id', $data['system_record_id'])
                    ->exists();
            }
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

    private function validateSourceRecord(BankReconciliation $period, array $data): void
    {
        $type = $data['system_record_type'] ?? null;
        $id = $data['system_record_id'] ?? null;

        if (($type === null) !== ($id === null)) {
            throw ValidationException::withMessages([
                'system_record_id' => 'A system record type and ID must be provided together.',
            ]);
        }

        if ($type === null) {
            return;
        }

        $model = match ($type) {
            'contribution' => Contribution::query()->find($id),
            'cash_disbursement' => CashDisbursement::query()->find($id),
            default => null,
        };

        if ($model === null) {
            throw ValidationException::withMessages([
                'system_record_id' => 'The selected system record does not exist in this workspace.',
            ]);
        }

        if ((int) $model->workspace_id !== (int) $period->workspace_id) {
            throw ValidationException::withMessages([
                'system_record_id' => 'The selected system record belongs to another workspace.',
            ]);
        }

        $recordDate = $model instanceof Contribution
            ? $model->received_at
            : $model->payment?->payment_date;
        if ($recordDate !== null && $recordDate->gt($period->period_end->endOfDay())) {
            throw ValidationException::withMessages([
                'system_record_id' => 'A future-dated system record cannot be assigned to this reconciliation period.',
            ]);
        }
    }

    public function update(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, array $data, User $user, ?UploadedFile $evidence = null): ReconciliationDiscrepancy
    {
        return $this->withEvidence($evidence, 'bank-reconciliation/discrepancies/'.$period->id, 'supporting_evidence', function ($path) use ($period, $discrepancy, $data, $user) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            $discrepancy = ReconciliationDiscrepancy::query()->lockForUpdate()->findOrFail($discrepancy->id);
            if ($period->isLocked() || (int) $discrepancy->bank_reconciliation_id !== (int) $period->id || $discrepancy->resolution_status !== 'unresolved') {
                throw ValidationException::withMessages(['discrepancy' => 'Only unresolved discrepancies in an editable period can be updated.']);
            }
            if ($path !== null) {
                $data['evidence_path'] = $path;
            }
            $previousReviewStatus = $discrepancy->admin_review_status;
            $previousReviewNote = $discrepancy->admin_review_note;
            $discrepancy->fill([
                ...$data,
                'admin_review_status' => 'pending',
                'admin_reviewed_by' => null,
                'admin_reviewed_at' => null,
                'admin_review_note' => null,
                'resolved_by' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? $user->id : null,
                'resolved_at' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? now() : null,
            ]);
            $discrepancy->save();
            SecurityActivityEvent::query()->create(['actor_id' => $user->id, 'action' => 'bank_reconciliation_discrepancy_updated', 'summary' => "{$user->name} updated and resubmitted discrepancy #{$discrepancy->id} for Admin review.", 'metadata' => ['bank_reconciliation_id' => $period->id, 'reconciliation_discrepancy_id' => $discrepancy->id, 'type' => $discrepancy->type, 'custom_type' => $discrepancy->custom_type, 'resubmitted_from' => $previousReviewStatus, 'previous_admin_review_note' => $previousReviewNote], 'occurred_at' => now()]);
            app(ReconciliationPeriodService::class)->recalculate($period->fresh());
            return $discrepancy->fresh();
        });
    }

    public function review(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, string $decision, ?string $note, User $user): ReconciliationDiscrepancy
    {
        if (! in_array($decision, ['accepted', 'correction_requested'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose a valid discrepancy review decision.']);
        }

        return DB::transaction(function () use ($period, $discrepancy, $decision, $note, $user) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            $discrepancy = ReconciliationDiscrepancy::query()->lockForUpdate()->findOrFail($discrepancy->id);
            if ($period->isLocked() || (int) $discrepancy->bank_reconciliation_id !== (int) $period->id) {
                throw ValidationException::withMessages(['discrepancy' => 'Discrepancies in locked periods cannot be reviewed.']);
            }
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

            // Accounting-adjustment amounts are fixed when reported; Admin acceptance of the
            // explanation is the only resolution requirement, so no further Staff action is needed.
            // Timing differences are deliberately left unresolved: they only resolve once the bank
            // transaction actually clears (or legitimately carries forward), which Admin acceptance
            // alone does not establish.
            if ($decision === 'accepted' && $discrepancy->classification === 'accounting_adjustment' && $discrepancy->resolution_status === 'unresolved') {
                $resolvedAt = now();
                $discrepancy->update([
                    'resolution_status' => 'resolved',
                    'resolved_by' => $user->id,
                    'resolved_at' => $resolvedAt,
                ]);

                SecurityActivityEvent::query()->create([
                    'actor_id' => $user->id,
                    'action' => 'bank_reconciliation_discrepancy_resolved',
                    'summary' => "{$user->name} resolved discrepancy #{$discrepancy->id} automatically upon Admin acceptance.",
                    'metadata' => [
                        'bank_reconciliation_id' => $period->id,
                        'reconciliation_discrepancy_id' => $discrepancy->id,
                        'resolved_by' => $user->id,
                        'resolved_at' => $resolvedAt->toIso8601String(),
                        'auto_resolved' => true,
                    ],
                    'occurred_at' => $resolvedAt,
                ]);

                app(ReconciliationPeriodService::class)->recalculate($period->fresh());
            }

            return $discrepancy->fresh(['reporter', 'adminReviewer', 'resolver']);
        });
    }

    public function resolve(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, string $explanation, ?string $evidencePath, User $user, ?UploadedFile $evidence = null): ReconciliationDiscrepancy
    {
        return $this->withEvidence($evidence, 'bank-reconciliation/discrepancies/'.$period->id.'/resolutions', 'resolution_evidence', function ($path) use ($period, $discrepancy, $explanation, $evidencePath, $user) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            $discrepancy = ReconciliationDiscrepancy::query()->lockForUpdate()->findOrFail($discrepancy->id);
            if ($period->isLocked() || (int) $discrepancy->bank_reconciliation_id !== (int) $period->id) {
                throw ValidationException::withMessages(['discrepancy' => 'Discrepancies in locked periods cannot be resolved.']);
            }
            if ($discrepancy->admin_review_status !== 'accepted' || $discrepancy->resolution_status !== 'unresolved') {
                throw ValidationException::withMessages(['discrepancy' => 'Only unresolved discrepancies accepted by Admin can be resolved.']);
            }
            $evidencePath = $path ?? $evidencePath;
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

    private function withEvidence(?UploadedFile $file, string $directory, string $field, Closure $action): ReconciliationDiscrepancy
    {
        $path = null;

        try {
            return DB::transaction(function () use ($file, $directory, $field, $action, &$path) {
                if ($file !== null) {
                    $storedPath = Storage::disk('local')->putFile($directory, $file);
                    if (! is_string($storedPath) || $storedPath === '') {
                        throw ValidationException::withMessages([$field => 'The evidence could not be stored. Please try uploading it again.']);
                    }
                    $path = $storedPath;
                }

                return $action($path);
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
