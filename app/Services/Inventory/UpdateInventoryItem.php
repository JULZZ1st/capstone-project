<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateInventoryItem
{
    public function handle(InventoryItem $inventoryItem, array $data, User $user, ResolveInventoryClassification $resolver): InventoryItem
    {
        return DB::transaction(function () use ($inventoryItem, $data, $user, $resolver): InventoryItem {
            $item = InventoryItem::query()->whereKey($inventoryItem->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('update', $item);

            if ($data['status'] !== $item->status && in_array('archived', [$data['status'], $item->status], true)) {
                Gate::forUser($user)->authorize('archive', $item);
            }

            if ($data['unit'] !== $item->unit && ! $item->canChangeUnit()) {
                throw ValidationException::withMessages([
                    'unit' => 'The unit cannot change after stock or history has been recorded. Use a separate inventory item for a different unit.',
                ]);
            }

            // Retaining an existing classification is allowed even after it is deactivated.
            $category = $data['category'] === $item->category
                ? InventoryCategory::query()->where('key', $item->category)->first()
                : $resolver->category($user, $data['category'], $data['custom_category'] ?? null);

            if ($category === null) {
                throw ValidationException::withMessages(['category' => 'The current category is unavailable. Select an active category.']);
            }

            $categoryChanged = $category->key !== $item->category;

            if (! array_key_exists('subcategory_selection', $data)) {
                $subcategory = $categoryChanged ? null : $item->subcategory;
            } elseif (! $categoryChanged && $data['subcategory_selection'] === $item->subcategory?->key) {
                $subcategory = $item->subcategory;
            } else {
                $subcategory = $resolver->subcategory($user, $category, $data['subcategory_selection'], $data['custom_subcategory'] ?? null);
            }

            $item->update([
                ...$data,
                'category' => $category->key,
                'inventory_subcategory_id' => $subcategory?->id,
                'updated_by' => $user->id,
            ]);

            return $item;
        });
    }
}
