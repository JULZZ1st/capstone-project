<?php

namespace App\Models;

use App\Models\Concerns\WorkspaceScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementVoucher extends Model
{
    use WorkspaceScoped;

    protected $fillable = [
        'workspace_id',
        'cash_disbursement_id',
        'document_type',
        'review_status',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
        'review_reason',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(CashDisbursement::class, 'cash_disbursement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
