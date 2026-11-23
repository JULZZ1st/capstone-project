<?php

namespace App\Models;

use App\Models\Concerns\WorkspaceScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDisbursementPayment extends Model
{
    use WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'bank_account',
        'cash_disbursement_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'released_by',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(CashDisbursement::class, 'cash_disbursement_id');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function scopeForBankAccount($query, ?string $bankAccount)
    {
        $primary = config('bank_reconciliation.bank_accounts.0');

        return $query->where(function ($query) use ($bankAccount, $primary) {
            $query->where('bank_account', $bankAccount);
            if ($bankAccount === $primary) {
                $query->orWhereNull('bank_account');
            }
        });
    }
}
