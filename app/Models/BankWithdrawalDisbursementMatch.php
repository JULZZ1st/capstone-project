<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankWithdrawalDisbursementMatch extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'bank_ledger_entry_id',
        'cash_disbursement_id',
    ];

    public function bankLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(BankLedgerEntry::class);
    }

    public function cashDisbursement(): BelongsTo
    {
        return $this->belongsTo(CashDisbursement::class);
    }
}
