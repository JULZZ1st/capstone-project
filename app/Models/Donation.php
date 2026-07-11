<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_id',
        'donation_type',
        'amount',
        'description',
        'received_at',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }
}
