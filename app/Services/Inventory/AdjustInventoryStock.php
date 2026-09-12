<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustInventoryStock
{
    public function handle(InventoryItem $inventoryItem, array $data, User $user): InventoryItem
    {
        return DB::transaction(function () use ($inventoryItem, $data, $user) {
            $item = InventoryItem::query()
                ->whereKey($inventoryItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->status !== config('inventory.defaults.status')) {
                throw ValidationException::withMessages([
                    'movement_type' => 'Stock can only be adjusted for active inventory items.',
                ]);
            }

            $quantity = (float) $data['quantity'];
            $quantityBefore = (float) $item->current_quantity;
            $quantityAfter = match ($data['movement_type']) {
                'stock_in' => $quantityBefore + $quantity,
                'stock_out' => $quantityBefore - $quantity,
                'adjustment' => $quantityBefore + $quantity,
            };

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock out cannot reduce current stock below zero.',
                ]);
            }

            $item->stockMovements()->create([
                'created_by' => $user->id,
                'movement_type' => $data['movement_type'],
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => $data['reason'],
                'reference_number' => Arr::get($data, 'reference_number'),
                'remarks' => Arr::get($data, 'remarks'),
            ]);

            $item->forceFill([
                'quantity' => (int) round($quantityAfter),
                'current_quantity' => $quantityAfter,
                'last_movement_at' => now(),
                'updated_by' => $user->id,
            ])->save();

            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'inventory_stock_'.$data['movement_type'],
                'summary' => "{$user->name} recorded a {$data['movement_type']} movement of {$quantity} for {$item->name}.",
                'metadata' => [
                    'inventory_item_id' => $item->id,
                    'movement_type' => $data['movement_type'],
                    'quantity' => $quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'reason' => $data['reason'],
                ],
                'occurred_at' => now(),
            ]);

            return $item;
        });
    }
}
