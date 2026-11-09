<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
