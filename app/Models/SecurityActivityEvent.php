<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityActivityEvent extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'summary',
        'metadata',
        'ip_address',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeAction(Builder $query, ?string $action): Builder
    {
        return $query->when($action, fn (Builder $query) => $query->where('action', $action));
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('summary', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('actor', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
            });
        });
    }

    public function actionLabel(): string
    {
        return str($this->action)->replace('_', ' ')->title()->toString();
    }
}
