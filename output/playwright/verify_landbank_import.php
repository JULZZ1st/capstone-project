<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$query = App\Models\BankLedgerEntry::query()->where('statement_id', 'like', 'landbank-%');
$file = Illuminate\Http\UploadedFile::fake()->createWithContent(
    'landbank-sample-10-transactions.xlsx',
    file_get_contents(__DIR__.'/../../tests/Fixtures/BankReconciliation/landbank-sample-10-transactions.xlsx'),
);
$importer = app(App\Services\BankReconciliation\ImportBankStatement::class);
$preview = $importer->previewLandbank($file);
echo json_encode([
    'database' => config('database.connections.'.config('database.default').'.database'),
    'all_rows' => App\Models\BankLedgerEntry::query()->count(),
    'statement_ids' => App\Models\BankLedgerEntry::query()->pluck('statement_id')->all(),
    'landbank_rows' => (clone $query)->count(),
    'deposits' => (clone $query)->where('transaction_type', 'deposit')->count(),
    'withdrawals' => (clone $query)->where('transaction_type', 'withdrawal')->count(),
    'total' => (clone $query)->sum('amount'),
    'audit_events' => App\Models\SecurityActivityEvent::query()->where('action', 'bank_statement_imported')->count(),
    'users' => App\Models\User::query()->get(['id', 'email', 'name'])->toArray(),
    'preview_rows' => count($preview['rows']),
    'preview_errors' => $preview['errors'],
    'preview_warnings' => $preview['warnings'],
    'duplicate_checks' => array_map(static function (array $row): bool {
        $query = App\Models\BankLedgerEntry::query()
            ->where('transaction_type', $row['transaction_type'])
            ->whereDate('transaction_date', $row['transaction_date'])
            ->where('amount', (float) $row['amount']);
        return filled($row['reference_number'])
            ? $query->where('reference_number', $row['reference_number'])->exists()
            : $query->where('description', $row['description'])->exists();
    }, $preview['rows']),
]).PHP_EOL;
