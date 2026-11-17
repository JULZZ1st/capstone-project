<?php

namespace App\Services\Inventory;

use App\Models\Contribution;
use App\Models\ContributionItem;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostContributionToInventory
{
    /**
     * Post a received in-kind contribution exactly once per contribution item.
     *
     * This method deliberately does not handle distribution or stock-out. It
     * only records the receipt represented by the contribution item.
     */
    public function handle(Contribution $contribution, User $user): void
    {
        if (! $contribution->isInKind() || $contribution->status !== 'received') {
            return;
        }

        DB::transaction(function () use ($contribution, $user): void {
            $items = ContributionItem::query()
                ->where('contribution_id', $contribution->id)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $this->postItem($contribution, $item, $user);
            }
        });
    }

    private function postItem(Contribution $contribution, ContributionItem $item, User $user): void
    {
        $quantity = (float) $item->quantity;

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'items' => "The quantity for {$item->name} must be greater than zero.",
            ]);
        }

        $existingMovement = InventoryStockMovement::query()
            ->where('contribution_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        if ($existingMovement) {
            if ((int) $item->inventory_item_id !== (int) $existingMovement->inventory_item_id) {
                $item->forceFill(['inventory_item_id' => $existingMovement->inventory_item_id])->save();
            }

            return;
        }

        $inventoryItem = $item->inventory_item_id
            ? InventoryItem::query()->whereKey($item->inventory_item_id)->lockForUpdate()->first()
            : $this->findMatchingInventoryItem($item);

        if (! $inventoryItem) {
            $inventoryItem = $this->createInventoryItem($contribution, $item, $user);
        }

        if ($inventoryItem->status !== config('inventory.defaults.status')) {
            throw ValidationException::withMessages([
                'items' => "The inventory item for {$item->name} is not active and cannot receive stock.",
            ]);
        }

        $quantityBefore = (float) $inventoryItem->current_quantity;
        $quantityAfter = $quantityBefore + $quantity;

        $movement = InventoryStockMovement::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'contribution_item_id' => $item->id,
            'created_by' => $user->id,
            'movement_type' => 'stock_in',
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reason' => 'new_donation',
            'reference_number' => $contribution->receipt_number,
            'remarks' => "Automatic stock-in from contribution item #{$item->id}.",
        ]);

        if (! $movement->exists) {
            throw new \RuntimeException('The inventory stock movement could not be recorded.');
        }

        $inventoryItem->forceFill([
            'quantity' => (int) round($quantityAfter),
            'current_quantity' => $quantityAfter,
            'last_movement_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        $item->forceFill(['inventory_item_id' => $inventoryItem->id])->save();
    }

    private function findMatchingInventoryItem(ContributionItem $item): ?InventoryItem
    {
        $category = $this->inventoryCategory($item->category);
        $name = trim($item->name);

        return InventoryItem::query()
            ->active()
            ->where('category', $category)
            ->where('unit', $item->unit)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->lockForUpdate()
            ->first();
    }

    private function createInventoryItem(Contribution $contribution, ContributionItem $item, User $user): InventoryItem
    {
        $category = $this->inventoryCategory($item->category);
        $categoryExists = InventoryCategory::query()
            ->active()
            ->where('key', $category)
            ->exists();

        if (! $categoryExists) {
            throw ValidationException::withMessages([
                'items' => "The inventory category for {$item->name} is not available.",
            ]);
        }

        $quantity = (float) $item->quantity;
        $unitValue = $item->estimated_value !== null && $quantity > 0
            ? round((float) $item->estimated_value / $quantity, 2)
            : null;

        return InventoryItem::query()->create([
            'name' => trim($item->name),
            'category' => $category,
            'unit' => $item->unit,
            'quantity' => 0,
            'current_quantity' => 0,
            'minimum_reserve_quantity' => 0,
            'estimated_unit_value' => $unitValue,
            'expires_at' => $item->expiration_date,
            'batch_number' => "DON-{$contribution->id}-{$item->id}",
            'storage_location' => 'Main Storage',
            'description' => "Received from contribution {$contribution->receipt_number}.",
            'notes' => "Created automatically from contribution item #{$item->id}.",
            'received_at' => $contribution->received_at ?? now(),
            'status' => config('inventory.defaults.status'),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function inventoryCategory(string $contributionCategory): string
    {
        return config("inventory.contribution_category_map.{$contributionCategory}")
            ?? 'other';
    }
}
