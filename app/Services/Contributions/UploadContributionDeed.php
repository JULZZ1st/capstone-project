<?php

namespace App\Services\Contributions;

use App\Models\Contribution;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadContributionDeed
{
    public function handle(Contribution $contribution, UploadedFile $file, User $actor): void
    {
        $storedPath = null;

        try {
            DB::transaction(function () use ($contribution, $file, $actor, &$storedPath): void {
                $contribution = Contribution::query()->lockForUpdate()->findOrFail($contribution->id);
                Gate::forUser($actor)->authorize('uploadDeed', $contribution);

                $path = Storage::disk('local')->putFile("contributions/{$contribution->id}/documents", $file);

                if (! is_string($path) || $path === '') {
                    throw ValidationException::withMessages([
                        'deed_of_donation' => 'The deed could not be stored. Please try uploading it again.',
                    ])->errorBag('deedUpload')->redirectTo(route('donations.show', $contribution));
                }

                $storedPath = $path;
                $document = $contribution->evidence()->create([
                    'document_type' => 'deed_of_donation',
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'uploaded_by' => $actor->id,
                ]);

                // Keep the original exception explanation as part of the record.
                $contribution->update(['document_review_status' => 'complete']);

                SecurityActivityEvent::query()->create([
                    'actor_id' => $actor->id,
                    'action' => 'cash_donation_deed_uploaded',
                    'summary' => "{$actor->name} uploaded the Deed of Donation for {$contribution->receipt_number}.",
                    'metadata' => ['contribution_id' => $contribution->id, 'evidence_id' => $document->id],
                    'ip_address' => request()->ip(),
                    'occurred_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }
}
