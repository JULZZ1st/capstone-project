<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashDisbursement extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'amount',
        'purpose',
        'recipient',
        'payee_address',
        'province',
        'city_municipality',
        'barangay',
        'payee_tin',
        'fund_source',
        'obr_number',
        'dv_number',
        'treasurer_certified_by',
        'treasurer_certified_at',
        'treasurer_certification_notes',
        'requested_date',
        'notes',
        'supporting_reference',
        'requested_by',
        'approved_by',
        'status',
        'signed_voucher_status',
        'signed_voucher_request_reason',
        'signed_voucher_requested_by',
        'signed_voucher_requested_at',
        'payment_status',
        'disbursed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_date' => 'date',
            'treasurer_certified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'voided_at' => 'datetime',
            'signed_voucher_requested_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function treasurerCertifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treasurer_certified_by');
    }

    public function hasTreasurerCertification(): bool
    {
        return $this->treasurer_certified_at !== null && $this->treasurer_certified_by !== null;
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function withdrawalMatch(): HasOne
    {
        return $this->hasOne(BankWithdrawalDisbursementMatch::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(DisbursementVoucher::class, 'cash_disbursement_id');
    }

    public function latestVoucher(): HasOne
    {
        return $this->hasOne(DisbursementVoucher::class, 'cash_disbursement_id')->latestOfMany();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(CashDisbursementPayment::class, 'cash_disbursement_id');
    }

    public function paymentAmount(): float
    {
        return (float) ($this->payment?->amount ?? 0);
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->amount - $this->paymentAmount());
    }

    public function hasSignedVoucherForApproval(): bool
    {
        return $this->signed_voucher_status === 'pending_review'
            || $this->signed_voucher_status === 'approved'
            || $this->latestVoucher()->whereIn('review_status', ['pending_review', 'approved'])->exists();
    }

    public function scopeApprovedActive($query)
    {
        return $query
            ->where('status', 'approved')
            ->whereNull('voided_at');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
