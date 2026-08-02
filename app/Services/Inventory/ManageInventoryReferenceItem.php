<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageInventoryReferenceItem
{
    public function create(string $modelClass, array $data): Model
    {
        return $modelClass::query()->create([
            'key' => $data['key'],
            'label' => $data['label'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Model $item, array $data): Model
    {
        $item->update([
            'key' => $data['key'],
            'label' => $data['label'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $item;
    }

    public function delete(Model $item): void
    {
        DB::transaction(function () use ($item) {
            $column = $item::inventoryItemColumn();

            $inUse = InventoryItem::query()->where($column, $item->key)->exists();

            if ($inUse) {
                throw ValidationException::withMessages([
                    'key' => "This {$column} is used by existing inventory items. Deactivate it instead of deleting.",
                ]);
            }

            $item->delete();
        });
    }
}
