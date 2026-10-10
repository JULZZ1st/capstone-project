<?php

namespace App\Services\Reports;

use App\Models\SecurityActivityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuditTrailReportBuilder
{
    public function build(?string $from = null, ?string $to = null): array
    {
        $query = SecurityActivityEvent::query()
            ->with(['actor:id,name', 'user:id,name'])
            ->latest('occurred_at');

        if ($from) {
            $query->where('occurred_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('occurred_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $events = $query->get()->map(function (SecurityActivityEvent $event): array {
            return [
                'user' => $event->actor?->name ?? $event->user?->name ?? 'System',
                'action' => $event->actionLabel(),
                'module' => $this->moduleFor($event->action),
                'occurred_at' => $event->occurred_at,
                'summary' => $event->summary,
            ];
        });

        return compact('events', 'from', 'to');
    }

    private function moduleFor(string $action): string
    {
        $prefix = Str::before($action, '_');

        return match ($prefix) {
            'inventory' => 'Inventory',
            'staff', 'security', 'admin' => 'System Administration',
            'bank' => 'Bank Reconciliation',
            'disbursement' => 'Fund Requests',
            'contribution', 'donation' => 'Donations',
            'auth', 'login', 'logout' => 'Authentication',
            default => 'System',
        };
    }
}
