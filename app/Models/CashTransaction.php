<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_name',
        'amount',
        'purpose',
        'received_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', config('dashboard.cash_statuses.pending'));
    }

    public function scopeVerified($query)
    {
        return $query->whereIn('status', [
            config('dashboard.cash_statuses.verified'),
            config('dashboard.cash_statuses.approved'),
        ]);
    }
}
