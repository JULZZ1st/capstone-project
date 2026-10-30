<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionItem extends Model
{
    use HasFactory;
    use Concerns\WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'contribution_id',
        'name',
        'category',
        'category_description',
        'subcategory',
        'quantity',
        'unit',
        'condition',
        'expiration_date',
        'brand',
        'model',
        'serial_number',
        'estimated_value',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'expiration_date' => 'date',
        ];
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
