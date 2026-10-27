<?php

namespace App\Services\Contributions;

use App\Models\Contribution;
use App\Models\User;
use App\Services\Inventory\ResolveInventoryClassification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use RuntimeException;

class LogContribution
{
    public function handle(array $data, User $user, ResolveInventoryClassification $resolver): Contribution
    {
        $photoPath = null;

        try {
            return DB::transaction(function () use ($data, $user, $resolver, &$photoPath) {
                $type = $data['donation_type'];

                $contribution = Contribution::query()->create([
                    'donor_id' => $data['donor_id'],
                    'created_by' => $user->id,
                    'type' => $type,
                    'status' => config("donations.default_status.{$type}"),
                    'amount' => $type === 'cash' ? $data['amount'] : null,
                    'payment_method' => $type === 'cash' ? $data['payment_method'] : null,
                    'payment_method_name' => $type === 'cash' && ($data['payment_method'] ?? null) === 'other'
                        ? ($data['payment_method_name'] ?? null)
                        : null,
                    'reference_number' => $type === 'cash' && in_array($data['payment_method'] ?? null, config('donations.electronic_payment_methods'), true)
                        ? ($data['reference_number'] ?? null)
                        : null,
                    'notes' => $data['notes'] ?? null,
                    'received_at' => $data['received_at'] ?? now(),
                ]);

                $contribution->forceFill([
                    'receipt_number' => $this->receiptNumberFor($contribution),
                ])->save();

                if ($photo = Arr::get($data, 'photo')) {
                    $photoPath = $photo->store('contribution-photos', 'local');

                    if (! is_string($photoPath)) {
                        throw new RuntimeException('The contribution photo could not be stored.');
                    }

                    $contribution->forceFill(['photo_path' => $photoPath])->save();
                }

                if ($type === 'in_kind') {
                    $contribution->items()->createMany(
                        collect($data['items'])->map(function ($item) use ($user, $resolver) {
                            $category = $resolver->category($user, $item['category'], Arr::get($item, 'custom_category'));
                            $subcategory = $resolver->subcategory($user, $category, Arr::get($item, 'subcategory'), Arr::get($item, 'custom_subcategory'));

                            return [
                                'name' => $item['name'],
                                'category' => $category->key,
                                'subcategory' => $subcategory?->key,
                                'quantity' => $item['quantity'],
                                'unit' => $item['unit'],
                                'condition' => $item['condition'],
                                'estimated_value' => Arr::get($item, 'estimated_value'),
                                'notes' => Arr::get($item, 'notes'),
                            ];
                        })->all()
                    );
                }

                return $contribution;
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('local')->delete($photoPath);
            }

            throw $exception;
        }
    }

    private function receiptNumberFor(Contribution $contribution): string
    {
        return sprintf(
            'DON-%s-%06d',
            $contribution->received_at->format('Ymd'),
            $contribution->id
        );
    }
}
