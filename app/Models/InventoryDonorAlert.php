<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDonorAlert extends Model
{
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id', 'inventory_item_id', 'status', 'triggered_at', 'handled_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return ['triggered_at' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
