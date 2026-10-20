<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'name',
        'category',
        'inventory_subcategory_id',
        'unit',
        'quantity',
        'current_quantity',
        'minimum_reserve_quantity',
        'estimated_unit_value',
        'expires_at',
        'batch_number',
        'storage_location',
        'description',
        'notes',
        'received_at',
        'last_audited_at',
        'last_movement_at',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:2',
            'minimum_reserve_quantity' => 'decimal:2',
            'estimated_unit_value' => 'decimal:2',
            'expires_at' => 'date',
            'received_at' => 'datetime',
            'last_audited_at' => 'datetime',
            'last_movement_at' => 'datetime',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(InventorySubcategory::class, 'inventory_subcategory_id');
    }

    public function scopeReceived($query)
    {
        return $query->where('status', config('dashboard.inventory_statuses.received'));
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $matchingCategories = collect(InventoryCategory::options())
            ->filter(fn (string $label, string $key) => str_contains(strtolower($label), strtolower($term)) || str_contains($key, strtolower($term)))
            ->keys()
            ->all();

        return $query->where(function ($query) use ($term, $matchingCategories) {
            $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('batch_number', 'like', "%{$term}%")
                ->orWhere('storage_location', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%");

            if ($matchingCategories !== []) {
                $query->orWhereIn('category', $matchingCategories);
            }
        });
    }

    public function scopeCategory($query, ?string $category)
    {
        return $category ? $query->where('category', $category) : $query;
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeActive($query)
    {
        return $query->where('status', config('inventory.defaults.status'));
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('current_quantity', '<=', 0);
    }

    public function scopeExpired($query)
    {
        return $query
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today())
            ->where('current_quantity', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query
            ->where('current_quantity', '>', 0)
            ->whereColumn('current_quantity', '<=', 'minimum_reserve_quantity');
    }

    public function scopeCriticalStock($query)
    {
        return $query
            ->where('current_quantity', '>', 0)
            ->whereRaw('current_quantity <= minimum_reserve_quantity * ?', [(float) config('inventory.critical_stock_ratio')]);
    }

    public function stockStatus(): string
    {
        $currentQuantity = (float) $this->current_quantity;
        $minimumReserve = (float) $this->minimum_reserve_quantity;
        $criticalThreshold = $minimumReserve * (float) config('inventory.critical_stock_ratio');

        if ($this->isExpired() && $currentQuantity > 0) {
            return 'expired';
        }

        if ($currentQuantity <= 0) {
            return 'out_of_stock';
        }

        if ($currentQuantity <= $criticalThreshold) {
            return 'critical_stock';
        }

        if ($currentQuantity <= $minimumReserve) {
            return 'low_stock';
        }

        return 'adequate';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && ! $this->expires_at->isToday();
    }

    public function canStockOut(float $quantity): bool
    {
        return (float) $this->current_quantity - $quantity >= 0;
    }

    public function stockProgressPercentage(): int
    {
        $minimumReserve = (float) $this->minimum_reserve_quantity;

        if ($minimumReserve <= 0) {
            return (float) $this->current_quantity > 0 ? 100 : 0;
        }

        return (int) min(100, round(((float) $this->current_quantity / $minimumReserve) * 100));
    }
}
