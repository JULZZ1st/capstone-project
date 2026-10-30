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
        return DB::transaction(function () use ($item, $data) {
            $item = $item->newQuery()->lockForUpdate()->findOrFail($item->getKey());
            if ($data['key'] !== $item->key && $this->isInUse($item)) {
                throw ValidationException::withMessages([
                    'key' => 'This key is used by existing inventory items. Update its label or deactivate it instead.',
                ]);
            }

            $item->update([
                'key' => $data['key'],
                'label' => $data['label'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $item;
        });
    }

    public function delete(Model $item): void
    {
        DB::transaction(function () use ($item) {
            $item = $item->newQuery()->lockForUpdate()->findOrFail($item->getKey());
            $column = $item::inventoryItemColumn();

            if ($this->isInUse($item)) {
                throw ValidationException::withMessages([
                    'key' => "This {$column} is used by existing inventory items. Deactivate it instead of deleting.",
                ]);
            }

            $item->delete();
        });
    }

    private function isInUse(Model $item): bool
    {
        // Categories and units are shared references, so protect usage across all workspaces.
        return InventoryItem::withoutGlobalScope('active_workspace')
            ->where($item::inventoryItemColumn(), $item->key)
            ->exists();
    }
}
