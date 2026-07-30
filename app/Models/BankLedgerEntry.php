<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'statement_id',
        'description',
        'reference_number',
        'amount',
        'transaction_date',
        'payment_method',
        'status',
        'matched_contribution_id',
        'matched_by',
        'matched_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'matched_at' => 'datetime',
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

    public function scopeNeedsReview($query)
    {
        return $query->where('status', '!=', 'reconciled');
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }
}
