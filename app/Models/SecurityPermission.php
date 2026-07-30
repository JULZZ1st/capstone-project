<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SecurityPermission extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'sort_order',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('granted_by')
            ->withTimestamps();
    }
}
