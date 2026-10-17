<?php

namespace App\Support\Workspaces;

use App\Models\Workspace;

class WorkspaceContext
{
    private ?Workspace $workspace = null;

    public function set(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function clear(): void
    {
        $this->workspace = null;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->getKey();
    }

    public function hasWorkspace(): bool
    {
        return $this->workspace !== null;
    }

    public function requireId(): int
    {
        abort_unless($this->id() !== null, 403, 'An authorized active workspace is required.');

        return (int) $this->id();
    }
}
