<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSecurityProfile extends Model
{
    protected $fillable = [
        'user_id',
        'two_factor_enabled',
        'two_factor_enforced',
        'two_factor_verified_at',
        'security_key_suffix',
    ];

    protected function casts(): array
    {
        return [
            'two_factor_enabled' => 'boolean',
            'two_factor_enforced' => 'boolean',
            'two_factor_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
