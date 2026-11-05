<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contribution extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'donor_id',
        'created_by',
        'verified_by',
        'type',
        'status',
        'amount',
        'purpose_type',
        'purpose_name',
        'payment_method',
        'payment_method_name',
        'reference_number',
        'check_number',
        'bank_name',
        'registered_bank_account',
        'official_receipt_number',
        'certificate_requested',
        'certificate_status',
        'certificate_issued_by',
        'certificate_issued_at',
        'deed_declined',
        'deed_decline_reason',
        'document_review_status',
        'document_request_reason',
        'documents_requested_by',
        'documents_requested_at',
        'donor_tin',
        'notes',
        'photo_path',
        'received_at',
        'verified_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
            'voided_at' => 'datetime',
            'certificate_requested' => 'boolean',
            'certificate_issued_at' => 'datetime',
            'deed_declined' => 'boolean',
            'documents_requested_at' => 'datetime',
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function certificateIssuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certificate_issued_by');
    }

    public function documentsRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'documents_requested_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContributionItem::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ContributionEvidence::class);
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(BankLedgerEntry::class, 'matched_contribution_id');
    }

    public function scopeEligibleForReconciliation($query)
    {
        return $query
            ->where('type', 'cash')
            ->whereIn('payment_method', config('donations.electronic_payment_methods'))
            ->where('status', 'verified')
            ->whereNull('voided_at')
            ->whereDoesntHave('ledgerEntry');
    }

    public function scopeType($query, ?string $type)
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($query) use ($term) {
            $query
                ->where('receipt_number', 'like', "%{$term}%")
                ->orWhere('reference_number', 'like', "%{$term}%")
                ->orWhere('official_receipt_number', 'like', "%{$term}%")
                ->orWhere('check_number', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")
                ->orWhereHas('donor', function ($query) use ($term) {
                    $query
                        ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('contact_number', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                })
                ->orWhereHas('items', function ($query) use ($term) {
                    $query
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%");
                });
        });
    }

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    public function verificationErrors(): array
    {
        $this->loadMissing('evidence');
        $documents = $this->evidence->pluck('document_type');
        $errors = [];

        if ($this->payment_method === 'bank_transfer'
            && ! $documents->intersect(['bank_transfer_proof', 'supporting_evidence'])->count()) {
            $errors['bank_transfer_proof'] = 'Bank Deposit/Transfer Proof must be uploaded before this donation can be verified.';
        }

        if (! $this->isCash()) {
            return $errors;
        }

        if (blank($this->official_receipt_number)) {
            $errors['official_receipt_number'] = 'Official Receipt Number is required before verification.';
        }
        if (! $documents->contains('official_receipt')) {
            $errors['official_receipt_document'] = 'Official Receipt upload is required before verification.';
        }
        if (! $documents->contains('certificate_of_acceptance')) {
            $errors['certificate_of_acceptance'] = 'Certificate of Acceptance is required before verification.';
        }
        if ((float) $this->amount >= 5000 && ! $documents->contains('deed_of_donation')) {
            $errors['deed_of_donation'] = 'This cash donation cannot be verified while the required Deed of Donation is missing. Review the donor exception and obtain the written document before verification.';
        }

        return $errors;
    }

    public function editableStatuses(): array
    {
        if ($this->ledger_entry_exists ?? $this->ledgerEntry()->exists()) {
            return [$this->status];
        }

        return array_values(array_filter(array_keys(config('donations.statuses')),
            fn (string $status) => $status !== 'verified' || $this->status === 'verified'));
    }

    public function paymentMethodLabel(): ?string
    {
        if ($this->payment_method === null) {
            return null;
        }

        if ($this->payment_method === 'other') {
            return $this->payment_method_name ?: 'Other';
        }

        return config("donations.payment_methods.{$this->payment_method}")
            ?? config("donations.legacy_payment_methods.{$this->payment_method}")
            ?? ucfirst($this->payment_method);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['pending', 'received'], true);
    }
}
