<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait WorkspaceScoped
{
    protected static function bootWorkspaceScoped(): void
    {
        static::addGlobalScope('active_workspace', function (Builder $builder): void {
            $context = app(WorkspaceContext::class);

            if ($context->hasWorkspace()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('workspace_id'),
                    $context->id(),
                );
            }
        });

        static::creating(function ($model): void {
            $context = app(WorkspaceContext::class);

            if ($model->getAttribute('workspace_id') === null && $context->hasWorkspace()) {
                $model->setAttribute('workspace_id', $context->id());
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace(Builder $query, Workspace|int $workspace): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('workspace_id'),
            $workspace instanceof Workspace ? $workspace->getKey() : $workspace,
        );
    }
}
