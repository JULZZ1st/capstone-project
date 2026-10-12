<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InKindAuditCsvExporter
{
    public function handle(Collection $movements, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($movements) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Item',
                'Category',
                'Subcategory',
                'Movement Type',
                'Quantity',
                'Unit',
                'Quantity Before',
                'Quantity After',
                'Reason',
                'Reference Number',
                'Recorded By',
                'Remarks',
            ]);

            foreach ($movements as $movement) {
                fputcsv($handle, [
                    $movement->created_at->format('Y-m-d H:i'),
                    $movement->inventoryItem?->name ?? 'Deleted inventory item',
                    $movement->inventoryItem?->category ?? '',
                    $movement->inventoryItem?->subcategory?->label ?? '',
                    str($movement->movement_type)->replace('_', ' ')->title(),
                    number_format((float) $movement->quantity, 2, '.', ''),
                    $movement->inventoryItem?->unit ?? '',
                    number_format((float) $movement->quantity_before, 2, '.', ''),
                    number_format((float) $movement->quantity_after, 2, '.', ''),
                    str($movement->reason)->replace('_', ' ')->title(),
                    $movement->reference_number ?? '',
                    $movement->creator?->name ?? 'Unknown user',
                    $movement->remarks ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
