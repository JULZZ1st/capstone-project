<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_type',
        'first_name',
        'last_name',
        'full_name',
        'contact_person',
        'address',
        'barangay_area',
        'contact_number',
        'preferred_contact_method',
        'email',
        'status',
        'notes',
    ];

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($query) use ($term) {
            $query
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('contact_number', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%")
                ->orWhere('contact_person', 'like', "%{$term}%")
                ->orWhere('barangay_area', 'like', "%{$term}%");
        });
    }

    public function scopeType($query, ?string $type)
    {
        if (! $type || ! array_key_exists($type, config('donors.types'))) {
            return $query;
        }

        return $query->where('donor_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('status', config('donors.defaults.status'));
    }

    public function scopeWithDonationMetrics($query)
    {
        return $query
            ->withCount(['contributions as donations_count' => fn ($query) => $query->whereNull('voided_at')])
            ->withSum([
                'contributions as total_cash_donations' => fn ($query) => $query->where('type', 'cash')->whereNull('voided_at'),
            ], 'amount')
            ->withSum([
                'contributions as total_in_kind_donations' => fn ($query) => $query->where('type', 'in_kind')->whereNull('voided_at'),
            ], 'amount');
    }
}
