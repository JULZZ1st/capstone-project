<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_donations')) {
            foreach (DB::table('cash_donations')->orderBy('id')->get() as $donation) {
                $referenceNumber = 'LEGACY-CASH-'.$donation->id;

                if (DB::table('contributions')->where('reference_number', $referenceNumber)->exists()) {
                    continue;
                }

                $notes = trim((string) $donation->notes);
                if ($donation->proof_path) {
                    $notes = trim($notes."\nLegacy proof path: {$donation->proof_path}");
                }

                DB::table('contributions')->insert([
                    'donor_id' => $donation->donor_id,
                    'created_by' => $this->existingUserId($donation->recorded_by),
                    'type' => 'cash',
                    'status' => 'received',
                    'amount' => $donation->amount,
                    'payment_method' => 'cash',
                    'reference_number' => $referenceNumber,
                    'notes' => $notes ?: null,
                    'received_at' => $donation->donated_at,
                    'created_at' => $donation->created_at,
                    'updated_at' => $donation->updated_at,
                ]);
            }
        }

        if (! Schema::hasTable('in_kind_donations')) {
            return;
        }

        foreach (DB::table('in_kind_donations')->orderBy('id')->get() as $donation) {
            $referenceNumber = 'LEGACY-INKIND-'.$donation->id;

            if (DB::table('contributions')->where('reference_number', $referenceNumber)->exists()) {
                continue;
            }

            $contributionId = DB::table('contributions')->insertGetId([
                'donor_id' => $donation->donor_id,
                'created_by' => $this->existingUserId($donation->recorded_by),
                'type' => 'in_kind',
                'status' => 'received',
                'reference_number' => $referenceNumber,
                'notes' => $donation->notes,
                'received_at' => $donation->donated_at,
                'created_at' => $donation->created_at,
                'updated_at' => $donation->updated_at,
            ]);

            $itemNotes = trim((string) $donation->notes);
            if ($donation->inventory_item_id) {
                $itemNotes = trim($itemNotes."\nLegacy inventory item ID: {$donation->inventory_item_id}");
            }

            DB::table('contribution_items')->insert([
                'contribution_id' => $contributionId,
                'name' => $donation->item_name,
                'category' => $this->category($donation->category),
                'quantity' => $donation->quantity,
                'unit' => $this->unit($donation->unit),
                'condition' => 'good',
                'estimated_value' => null,
                'notes' => $itemNotes ?: null,
                'created_at' => $donation->created_at,
                'updated_at' => $donation->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        $contributionIds = DB::table('contributions')
            ->where('reference_number', 'like', 'LEGACY-INKIND-%')
            ->pluck('id');

        DB::table('contribution_items')->whereIn('contribution_id', $contributionIds)->delete();
        DB::table('contributions')->where('reference_number', 'like', 'LEGACY-%')->delete();
    }

    private function existingUserId(?int $userId): ?int
    {
        return $userId && DB::table('users')->where('id', $userId)->exists() ? $userId : null;
    }

    private function category(?string $category): string
    {
        $category = strtolower(trim((string) $category));
        $category = $category === 'medicine' ? 'medical_supplies' : $category;

        return array_key_exists($category, config('donations.item_categories')) ? $category : 'other';
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

        return array_key_exists($unit, config('donations.units')) ? $unit : 'other';
    }
};