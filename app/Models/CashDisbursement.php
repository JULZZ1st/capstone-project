<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'requested_date',
        'notes',
        'supporting_reference',
        'requested_by',
        'approved_by',
        'status',
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
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function withdrawalMatch(): HasOne
    {
        return $this->hasOne(BankWithdrawalDisbursementMatch::class);
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
