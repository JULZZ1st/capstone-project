<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'disbursed_to',
        'purpose',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'disbursed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'disbursed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($query) use ($term) {
            $query
                ->where('disbursed_to', 'like', "%{$term}%")
                ->orWhere('reference_number', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%");
        });
    }

    public function scopePurpose($query, ?string $purpose)
    {
        return $purpose ? $query->where('purpose', $purpose) : $query;
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn ($query) => $query->whereDate('disbursed_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('disbursed_at', '<=', $to));
    }
}
