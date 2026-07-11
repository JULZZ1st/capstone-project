<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'opening_balance',
        'cash_in',
        'cash_out',
        'closing_balance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opening_balance' => 'decimal:2',
            'cash_in' => 'decimal:2',
            'cash_out' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }
}
