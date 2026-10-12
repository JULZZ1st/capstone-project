<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legacy_inventory_items')) {
            foreach (DB::table('legacy_inventory_items')->orderBy('id')->get() as $item) {
                DB::table('inventory_items')->insertOrIgnore([
                    'id' => $item->id,
                    'name' => $item->item_name ?: 'Unnamed item',
                    'category' => $this->category($item->category),
                    'unit' => $this->unit($item->unit),
                    'quantity' => $item->quantity,
                    'current_quantity' => $item->quantity,
                    'minimum_reserve_quantity' => 0,
                    'storage_location' => 'Main Storage',
                    'status' => config('inventory.defaults.status'),
                    'last_movement_at' => $item->created_at,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }

        if (! Schema::hasTable('inventory_transactions')) {
            return;
        }

        foreach (DB::table('inventory_transactions')->orderBy('id')->get() as $transaction) {
            $referenceNumber = 'LEGACY-INV-TX-'.$transaction->id;

            if (DB::table('inventory_stock_movements')->where('reference_number', $referenceNumber)->exists()) {
                continue;
            }

            $movementType = in_array($transaction->type, ['stock_in', 'stock_out'], true)
                ? $transaction->type
                : 'adjustment';
            $quantity = (float) $transaction->quantity;
            $quantityAfter = (float) $transaction->balance_after;
            $quantityBefore = $movementType === 'stock_in'
                ? $quantityAfter - $quantity
                : $quantityAfter + $quantity;
            $legacyReference = trim((string) $transaction->reference_type.'#'.(string) $transaction->reference_id, '#');
            $remarks = trim((string) $transaction->notes);
            if ($legacyReference !== '') {
                $remarks = trim($remarks."\nLegacy reference: {$legacyReference}");
            }

            DB::table('inventory_stock_movements')->insert([
                'inventory_item_id' => $transaction->inventory_item_id,
                'created_by' => $this->existingUserId($transaction->recorded_by),
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => match ($movementType) {
                    'stock_in' => 'new_donation',
                    'stock_out' => 'distribution',
                    default => 'correction',
                },
                'reference_number' => $referenceNumber,
                'remarks' => $remarks ?: null,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('inventory_stock_movements')
            ->where('reference_number', 'like', 'LEGACY-INV-TX-%')
            ->delete();

        if (Schema::hasTable('legacy_inventory_items')) {
            DB::table('inventory_items')
                ->whereIn('id', DB::table('legacy_inventory_items')->select('id'))
                ->delete();
        }
    }

    private function existingUserId(?int $userId): ?int
    {
        return $userId && DB::table('users')->where('id', $userId)->exists() ? $userId : null;
    }

    private function category(?string $category): string
    {
        $category = strtolower(trim((string) $category));
        $category = $category === 'medicine' ? 'medical_supplies' : $category;

        return array_key_exists($category, config('inventory.categories')) ? $category : 'other';
    }

    private function unit(?string $unit): string
    {
        $unit = strtolower(trim((string) $unit));
        $unit = [
            'pcs' => 'piece',
            'sacks' => 'sack',
            'boxes' => 'box',
            'sets' => 'set',
        ][$unit] ?? $unit;

        return array_key_exists($unit, config('inventory.units')) ? $unit : 'other';
    }
};