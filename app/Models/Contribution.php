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
        'campaign_id',
        'created_by',
        'verified_by',
        'type',
        'status',
        'amount',
        'payment_method',
        'payment_method_name',
        'reference_number',
        'notes',
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
        ];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContributionItem::class);
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

    public function scopeCampaign($query, ?int $campaignId)
    {
        return $campaignId ? $query->where('campaign_id', $campaignId) : $query;
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
                ->orWhere('notes', 'like', "%{$term}%")
                ->orWhereHas('donor', function ($query) use ($term) {
                    $query
                        ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('contact_number', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                })
                ->orWhereHas('campaign', fn ($query) => $query->where('name', 'like', "%{$term}%"))
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
