<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventorySubcategory;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResolveInventoryClassification
{
    public function category(User $user, ?string $selectedKey, ?string $customLabel): InventoryCategory
    {
        if ($selectedKey !== '__other__') {
            return InventoryCategory::query()->active()->where('key', $selectedKey)->firstOrFail();
        }

        return $this->findOrCreateCategory($user, $customLabel);
    }

    public function subcategory(User $user, InventoryCategory $category, ?string $selectedKey, ?string $customLabel): ?InventorySubcategory
    {
        if (blank($selectedKey)) {
            return null;
        }

        if ($selectedKey !== '__other__') {
            return InventorySubcategory::query()
                ->active()
                ->where('inventory_category_id', $category->id)
                ->where('key', $selectedKey)
                ->firstOrFail();
        }

        $label = trim((string) $customLabel);

        if ($label === '') {
            throw ValidationException::withMessages(['custom_subcategory' => 'Specify a subcategory.']);
        }

        $existing = InventorySubcategory::query()
            ->where('inventory_category_id', $category->id)
            ->whereRaw('LOWER(label) = ?', [Str::lower($label)])
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                throw ValidationException::withMessages(['custom_subcategory' => 'The matching subcategory is inactive.']);
            }

            return $existing;
        }

        $this->ensureCanCreate($user, 'subcategory');

        return InventorySubcategory::query()->create([
            'inventory_category_id' => $category->id,
            'key' => $this->uniqueKey(InventorySubcategory::query()->where('inventory_category_id', $category->id), $label),
            'label' => $label,
            'is_active' => true,
        ]);
    }

    private function findOrCreateCategory(User $user, ?string $customLabel): InventoryCategory
    {
        $label = trim((string) $customLabel);

        if ($label === '') {
            throw ValidationException::withMessages(['custom_category' => 'Specify a category.']);
        }

        $existing = InventoryCategory::query()
            ->whereRaw('LOWER(label) = ?', [Str::lower($label)])
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                throw ValidationException::withMessages(['custom_category' => 'The matching category is inactive.']);
            }

            return $existing;
        }

        $this->ensureCanCreate($user, 'category');

        return InventoryCategory::query()->create([
            'key' => $this->uniqueKey(InventoryCategory::query(), $label),
            'label' => $label,
            'is_active' => true,
        ]);
    }

    private function uniqueKey($query, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'custom';
        $key = $base;
        $suffix = 2;

        while ($query->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    private function ensureCanCreate(User $user, string $type): void
    {
        if (! $user->can('create', InventoryCategory::class)) {
            throw ValidationException::withMessages([
                $type === 'category' ? 'custom_category' : 'custom_subcategory' => "You are not authorized to create a custom {$type}.",
            ]);
        }
    }
}