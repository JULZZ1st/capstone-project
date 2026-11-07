<?php

namespace App\Models;

use App\Models\Concerns\WorkspaceScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionEvidence extends Model
{
    use WorkspaceScoped;

    protected $table = 'contribution_evidence';

    protected $fillable = [
        'workspace_id',
        'contribution_id',
        'document_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
