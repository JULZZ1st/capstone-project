<?php

namespace App\Support\Workspaces;

use Illuminate\Validation\Rule;

class WorkspaceValidationRules
{
    public static function exists(string $table, string $column = 'id'): mixed
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        return Rule::exists($table, $column)->where(function ($query) use ($workspaceId): void {
            if ($workspaceId === null) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where('workspace_id', $workspaceId);
        });
    }

    public static function unique(string $table, string $column): mixed
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        return Rule::unique($table, $column)->where(function ($query) use ($workspaceId): void {
            if ($workspaceId === null) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where('workspace_id', $workspaceId);
        });
    }
}
