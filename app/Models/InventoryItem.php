<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'quantity',
        'description',
        'received_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function scopeReceived($query)
    {
        return $query->where('status', config('dashboard.inventory_statuses.received'));
    }
}
