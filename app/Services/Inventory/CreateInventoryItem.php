<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateInventoryItem
{
    public function handle(array $data, User $user): InventoryItem
    {
        return DB::transaction(function () use ($data, $user) {
            $initialQuantity = (float) $data['initial_quantity'];

            $item = InventoryItem::query()->create([
                'name' => $data['name'],
                'category' => $data['category'],
                'unit' => $data['unit'],
                'quantity' => (int) round($initialQuantity),
                'current_quantity' => $initialQuantity,
                'minimum_reserve_quantity' => $data['minimum_reserve_quantity'],
                'estimated_unit_value' => Arr::get($data, 'estimated_unit_value'),
                'expires_at' => Arr::get($data, 'expires_at'),
                'batch_number' => Arr::get($data, 'batch_number'),
                'storage_location' => $data['storage_location'],
                'description' => Arr::get($data, 'description'),
                'notes' => Arr::get($data, 'notes'),
                'received_at' => now(),
                'status' => $data['status'] ?? config('inventory.defaults.status'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($initialQuantity > 0) {
                $item->stockMovements()->create([
                    'created_by' => $user->id,
                    'movement_type' => 'stock_in',
                    'quantity' => $initialQuantity,
                    'quantity_before' => 0,
                    'quantity_after' => $initialQuantity,
                    'reason' => 'new_donation',
                    'remarks' => 'Initial inventory quantity.',
                ]);

                $item->forceFill(['last_movement_at' => now()])->save();
            }

            return $item;
        });
    }
}
