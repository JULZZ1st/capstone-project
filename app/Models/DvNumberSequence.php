<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DvNumberSequence extends Model
{
    protected $fillable = ['workspace_id', 'period', 'last_number'];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
