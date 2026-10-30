<?php

namespace App\Services\Contributions;

use App\Models\Contribution;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LogContribution
{
    public function handle(array $data, User $user): Contribution
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $user, &$storedPaths) {
                $type = $data['donation_type'];

                $contribution = Contribution::query()->create([
                    'donor_id' => $data['donor_id'],
                    'created_by' => $user->id,
                    'type' => $type,
                    'status' => config("donations.default_status.{$type}"),
                    'amount' => $type === 'cash' ? $data['amount'] : null,
                    'purpose_type' => $type === 'cash' ? ($data['purpose_type'] ?? 'general') : null,
                    'purpose_name' => $type === 'cash' ? ($data['purpose_name'] ?? null) : null,
                    'payment_method' => $type === 'cash' ? $data['payment_method'] : null,
                    'payment_method_name' => $type === 'cash' && ($data['payment_method'] ?? null) === 'other'
                        ? ($data['payment_method_name'] ?? null)
                        : null,
                    'reference_number' => $type === 'cash' && in_array($data['payment_method'] ?? null, ['bank_transfer', 'gcash', 'maya'], true)
                        ? ($data['reference_number'] ?? null)
                        : null,
                    'check_number' => $type === 'cash' && ($data['payment_method'] ?? null) === 'check'
                        ? ($data['check_number'] ?? null)
                        : null,
                    'bank_name' => $type === 'cash' && ($data['payment_method'] ?? null) === 'check'
                        ? ($data['bank_name'] ?? null)
                        : null,
                    'registered_bank_account' => $type === 'cash' && ($data['payment_method'] ?? null) === 'bank_transfer'
                        ? ($data['registered_bank_account'] ?? null)
                        : null,
                    'official_receipt_number' => $type === 'cash'
                        ? ($data['official_receipt_number'] ?? null)
                        : null,
                    'certificate_requested' => (bool) ($data['certificate_requested'] ?? false),
                    'certificate_status' => ($data['certificate_requested'] ?? false) ? 'pending' : 'not_requested',
                    'deed_declined' => $type === 'cash' && ($data['deed_declined'] ?? false),
                    'deed_decline_reason' => $type === 'cash' ? ($data['deed_decline_reason'] ?? null) : null,
                    'document_review_status' => $type !== 'cash'
                        ? 'not_required'
                        : (! empty($data['deed_declined']) && empty($data['deed_of_donation'])
                            ? 'missing_deed_admin_review'
                            : 'complete'),
                    'donor_tin' => $type === 'cash' && ($data['certificate_requested'] ?? false)
                        ? ($data['donor_tin'] ?? null)
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

                    $storedPaths[] = $photoPath;
                    $contribution->forceFill(['photo_path' => $photoPath])->save();
                }

                if ($type === 'in_kind') {
                    $contribution->items()->createMany(
                        collect($data['items'])->map(fn (array $item) => [
                            'name' => $item['name'],
                            'category' => $item['category'],
                            'category_description' => Arr::get($item, 'category_description'),
                            'subcategory' => null,
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'condition' => $item['condition'],
                            'expiration_date' => Arr::get($item, 'expiration_date'),
                            'brand' => Arr::get($item, 'brand'),
                            'model' => Arr::get($item, 'model'),
                            'serial_number' => Arr::get($item, 'serial_number'),
                            'estimated_value' => Arr::get($item, 'estimated_value'),
                            'notes' => Arr::get($item, 'notes'),
                        ])->all()
                    );
                }

                foreach ($data['evidence'] ?? [] as $file) {
                    $this->storeDocument($contribution, $file, 'supporting_evidence', $user, $storedPaths);
                }

                foreach ($data['bank_transfer_proof'] ?? [] as $file) {
                    $this->storeDocument($contribution, $file, 'bank_transfer_proof', $user, $storedPaths);
                }

                foreach ([
                    'official_receipt_document' => 'official_receipt',
                    'deed_of_donation' => 'deed_of_donation',
                    'certificate_of_acceptance' => 'certificate_of_acceptance',
                    'donation_item_list' => 'donation_item_list',
                    'deed_of_donation_acceptance' => 'deed_of_donation_acceptance',
                    'par_ics_acknowledgement' => 'par_ics_acknowledgement',
                ] as $input => $documentType) {
                    if (! empty($data[$input])) {
                        $this->storeDocument($contribution, $data[$input], $documentType, $user, $storedPaths);
                    }
                }

                if ($type === 'cash') {
                    $exception = $contribution->document_review_status === 'missing_deed_admin_review';
                    SecurityActivityEvent::query()->create([
                        'actor_id' => $user->id,
                        'action' => $exception ? 'cash_donation_deed_exception_recorded' : 'cash_donation_recorded',
                        'summary' => $exception
                            ? "{$user->name} recorded cash donation {$contribution->receipt_number} with Missing Deed – Admin Review: {$contribution->deed_decline_reason}"
                            : "{$user->name} recorded cash donation {$contribution->receipt_number} with required receipt documents.",
                        'metadata' => [
                            'contribution_id' => $contribution->id,
                            'deed_declined' => $contribution->deed_declined,
                            'deed_decline_reason' => $contribution->deed_decline_reason,
                            'document_review_status' => $contribution->document_review_status,
                        ],
                        'ip_address' => request()->ip(),
                        'occurred_at' => now(),
                    ]);
                }

                return $contribution;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
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

    private function storeDocument(Contribution $contribution, mixed $file, string $documentType, User $user, array &$storedPaths): void
    {
        $path = Storage::disk('local')->putFile("contributions/{$contribution->id}/documents", $file);
        $storedPaths[] = $path;

        $contribution->evidence()->create([
            'document_type' => $documentType,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }
}
