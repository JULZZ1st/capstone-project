<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class ImportBankStatement
{
    public function __construct(
        private readonly LedgerChannelResolver $channelResolver,
        private readonly ReconciliationPeriodGuard $periodGuard,
        private readonly ReconciliationPeriodService $periods,
    )
    {
    }

    public function handle(UploadedFile $file, ?User $user = null): int
    {
        $rows = $this->rows($file);

        if ($rows === []) {
            throw ValidationException::withMessages(['statement' => 'The bank statement contains no data rows.']);
        }

        $created = 0;

        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $index => $row) {
                $data = $this->normalize($row, $index + 2);
                $this->periodGuard->assertMutableDate($data['transaction_date']);

                if (BankLedgerEntry::query()->where('statement_id', $data['statement_id'])->exists()) {
                    throw ValidationException::withMessages([
                        'statement' => "Statement ID {$data['statement_id']} is already imported.",
                    ]);
                }

                if ($this->isDuplicateEntry($data)) {
                    throw ValidationException::withMessages([
                        'statement' => "A matching bank entry already exists on row ".($index + 2).'.',
                    ]);
                }

                BankLedgerEntry::query()->create($data);
                $created++;
            }

            BankReconciliation::query()
                ->whereIn('status', ['draft', 'ready_for_review'])
                ->get()
                ->each(fn (BankReconciliation $period) => $this->periods->recalculate($period));
        });

        if ($user !== null) {
            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'bank_statement_imported',
                'summary' => "{$user->name} imported {$created} bank transaction(s) for reconciliation.",
                'metadata' => ['imported_count' => $created],
                'occurred_at' => now(),
            ]);
        }

        return $created;
    }

    public function isLandbank(UploadedFile $file): bool
    {
        return $this->landbankHeader($this->landbankMatrix($file)) !== null;
    }

    /**
     * Parse a LANDBANK statement without writing anything. The returned payload
     * is safe to put in the session and is revalidated when confirmed.
     */
    public function previewLandbank(UploadedFile $file): array
    {
        $matrix = $this->landbankMatrix($file);
        $header = $this->landbankHeader($matrix);

        if ($header === null) {
            throw ValidationException::withMessages([
                'statement' => 'This is not a supported LANDBANK statement. Expected Date, Description, REF, Check No., Debit Amount, Credit Amount, Balance columns.',
            ]);
        }

        [$headerIndex, $columns] = $header;
        $beginning = $this->statementBalance($matrix, $headerIndex, 'beginning');
        $ending = $this->statementBalance($matrix, $headerIndex, 'ending');
        $previousBalance = $beginning;
        $rows = [];
        $errors = [];
        $warnings = [];
        $seenFingerprints = [];

        foreach (array_slice($matrix, $headerIndex + 1) as $offset => $values) {
            $line = $headerIndex + $offset + 2;
            $row = [];
            foreach ($columns as $name => $column) {
                $row[$name] = trim((string) ($values[$column] ?? ''));
            }

            if ($this->isLandbankBalanceRow($row)) {
                continue;
            }
            if (implode('', $row) === '') {
                continue;
            }

            $parsed = $this->normalizeLandbankRow($row, $line, $previousBalance);
            $rows[] = $parsed['data'];
            foreach ($parsed['errors'] as $error) {
                $errors[] = $error;
            }
            foreach ($parsed['warnings'] as $warning) {
                $warnings[] = $warning;
            }

            if ($parsed['data'] !== null) {
                $fingerprint = $parsed['data']['duplicate_fingerprint'];
                if (isset($seenFingerprints[$fingerprint])) {
                    $warnings[] = "Row {$line} duplicates row {$seenFingerprints[$fingerprint]} in this statement.";
                } else {
                    $seenFingerprints[$fingerprint] = $line;
                }
                if ($this->isDuplicateEntry($parsed['data'])) {
                    $warnings[] = "Row {$line} matches an existing bank entry and will not be imported.";
                }
                if ($parsed['data']['balance'] !== null) {
                    $previousBalance = $parsed['data']['balance'];
                }
            }
        }

        $lastBalance = collect($rows)->pluck('balance')->filter(fn ($value) => $value !== null)->last();
        if ($ending !== null && $lastBalance !== null && abs($ending - $lastBalance) >= 0.005) {
            $errors[] = sprintf('Ending balance %.2f does not match the last transaction balance %.2f.', $ending, $lastBalance);
        }
        if ($beginning === null) {
            $warnings[] = 'Beginning balance was not found; transaction direction was still taken only from the Debit/Credit columns, but a full running-balance check was not possible.';
        }
        if ($ending === null) {
            $warnings[] = 'Ending balance was not found; the last transaction balance was not independently confirmed.';
        }

        return [
            'bank' => 'LANDBANK',
            'beginning_balance' => $beginning,
            'ending_balance' => $ending,
            'rows' => $rows,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function confirmLandbank(array $preview, ?User $user = null): int
    {
        if (($preview['errors'] ?? []) !== []) {
            throw ValidationException::withMessages(['statement' => 'The LANDBANK statement still has validation errors and cannot be confirmed.']);
        }
        $rows = $preview['rows'] ?? [];
        if ($rows === []) {
            throw ValidationException::withMessages(['statement' => 'The LANDBANK statement contains no transaction rows.']);
        }

        $created = 0;
        DB::transaction(function () use ($rows, &$created): void {
            foreach ($rows as $data) {
                $this->periodGuard->assertMutableDate($data['transaction_date']);
                if (BankLedgerEntry::query()->where('statement_id', $data['statement_id'])->exists() || $this->isDuplicateEntry($data)) {
                    continue;
                }
                unset($data['balance'], $data['duplicate_fingerprint']);
                BankLedgerEntry::query()->create($data);
                $created++;
            }
            BankReconciliation::query()->whereIn('status', ['draft', 'ready_for_review'])
                ->get()->each(fn (BankReconciliation $period) => $this->periods->recalculate($period));
        });

        if ($user !== null) {
            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'bank_statement_imported',
                'summary' => "{$user->name} imported {$created} LANDBANK transaction(s) for reconciliation.",
                'metadata' => ['imported_count' => $created, 'bank' => 'LANDBANK'],
                'occurred_at' => now(),
            ]);
        }
        return $created;
    }

    private function normalizeLandbankRow(array $row, int $line, ?float $previousBalance): array
    {
        $errors = [];
        $warnings = [];
        $date = $this->parseDate($row['date'] ?? '');
        $debit = $this->parseAmount($row['debit_amount'] ?? '');
        $credit = $this->parseAmount($row['credit_amount'] ?? '');
        $balance = $this->parseAmount($row['balance'] ?? '');
        $hasDebit = $debit !== null && $debit != 0.0;
        $hasCredit = $credit !== null && $credit != 0.0;

        if ($date === null) $errors[] = "Invalid date on LANDBANK row {$line}.";
        if (blank($row['description'] ?? '')) $errors[] = "Missing description on LANDBANK row {$line}.";
        if ($debit === null && $credit === null) $errors[] = "A debit or credit amount is required on LANDBANK row {$line}; direction was not guessed.";
        if ($hasDebit && $hasCredit) $errors[] = "Both debit and credit are populated on LANDBANK row {$line}; direction is ambiguous.";
        if ($row['balance'] !== '' && $balance === null) $errors[] = "Invalid balance on LANDBANK row {$line}.";

        $type = $hasDebit ? 'withdrawal' : ($hasCredit ? 'deposit' : null);
        $amount = $hasDebit ? $debit : $credit;
        if ($previousBalance !== null && $balance !== null && $type !== null) {
            $expected = $type === 'withdrawal' ? $previousBalance - $amount : $previousBalance + $amount;
            if (abs($expected - $balance) >= 0.005) {
                $errors[] = sprintf('Running balance mismatch on LANDBANK row %d: expected %.2f, statement shows %.2f.', $line, $expected, $balance);
            }
        }
        if ($type === null || $date === null || blank($row['description'] ?? '')) {
            return ['data' => null, 'errors' => $errors, 'warnings' => $warnings];
        }

        $reference = trim($row['ref'] ?? '') ?: trim($row['check_no'] ?? '');
        $fingerprint = sha1(implode('|', [$date, $row['description'], $reference, number_format($amount, 2, '.', ''), $type]));
        return ['data' => [
            'statement_id' => 'landbank-'.$fingerprint,
            'duplicate_fingerprint' => $fingerprint,
            'description' => $row['description'],
            'reference_number' => $reference ?: null,
            'amount' => number_format($amount, 2, '.', ''),
            'transaction_date' => $date,
            'payment_method' => $this->channelResolver->resolve($row['description']),
            'transaction_type' => $type,
            'status' => $type === 'withdrawal' ? 'unreconciled' : 'unmatched',
            'balance' => $balance,
        ], 'errors' => $errors, 'warnings' => $warnings];
    }

    private function landbankHeader(array $matrix): ?array
    {
        $required = ['date', 'description', 'ref', 'check_no', 'debit_amount', 'credit_amount', 'balance'];
        foreach ($matrix as $index => $values) {
            $columns = [];
            foreach ($values as $column => $value) {
                $key = strtolower(trim((string) $value));
                $key = preg_replace('/[^a-z0-9]+/', '_', $key);
                $key = trim($key, '_');
                $key = ['check_no_' => 'check_no', 'check_number' => 'check_no', 'debit' => 'debit_amount', 'credit' => 'credit_amount'][$key] ?? $key;
                if (in_array($key, $required, true)) $columns[$key] = $column;
            }
            if (count(array_intersect($required, array_keys($columns))) === count($required)) return [$index, $columns];
        }
        return null;
    }

    private function landbankMatrix(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->xlsxMatrix($file->getRealPath())
            : array_map(fn ($row) => array_values($row), $this->readCsvMatrix($file->getRealPath()));
    }

    private function readCsvMatrix(string $path): array
    {
        $handle = fopen($path, 'rb'); $matrix = [];
        while (($values = fgetcsv($handle)) !== false) $matrix[] = $values;
        fclose($handle); return $matrix;
    }

    private function xlsxMatrix(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw ValidationException::withMessages(['statement' => 'The Excel file could not be opened.']);
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) foreach ((new SimpleXMLElement($xml))->si as $string) $shared[] = (string) ($string->t ?? implode('', array_map('strval', $string->r->t ?? [])));
        $sheet = new SimpleXMLElement($zip->getFromName('xl/worksheets/sheet1.xml')); $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') $value = $shared[(int) $value] ?? '';
                $values[$this->columnIndex((string) $cell['r']) - 1] = $value;
            }
            if ($values !== []) { ksort($values); $matrix[] = array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values); }
        }
        $zip->close(); return $matrix;
    }

    private function statementBalance(array $matrix, int $headerIndex, string $kind): ?float
    {
        $indexes = $kind === 'beginning'
            ? range(0, max(0, $headerIndex - 1))
            : range($headerIndex + 1, max($headerIndex + 1, count($matrix) - 1));
        foreach ($indexes as $i) {
            if (! isset($matrix[$i])) continue;
            $text = strtolower(implode(' ', $matrix[$i]));
            if (str_contains($text, $kind.' balance')) {
                foreach (array_reverse($matrix[$i]) as $value) if (($amount = $this->parseAmount((string) $value)) !== null) return $amount;
            }
        }
        return null;
    }

    private function isLandbankBalanceRow(array $row): bool
    {
        $text = strtolower(implode(' ', $row));
        return str_contains($text, 'beginning balance') || str_contains($text, 'ending balance') || str_contains($text, 'closing balance');
    }

    private function parseAmount(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = str_replace([',', '₱', 'PHP', ' '], '', trim($value, '()'));
        if (! is_numeric($value)) return null;
        return ($negative ? -1 : 1) * (float) $value;
    }

    private function parseDate(?string $value): ?string
    {
        try { return is_numeric($value) ? Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString() : Carbon::parse($value)->toDateString(); }
        catch (\Throwable) { return null; }
    }

    private function rows(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->xlsxRows($file->getRealPath())
            : $this->csvRows($file->getRealPath());
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($values, count($headers), null));
        }

        fclose($handle);

        return $rows;
    }

    private function xlsxRows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['statement' => 'The Excel file could not be opened.']);
        }

        $sharedStrings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach ((new SimpleXMLElement($xml))->si as $string) {
                $sharedStrings[] = (string) ($string->t ?? implode('', array_map('strval', $string->r->t ?? [])));
            }
        }

        $sheet = new SimpleXMLElement($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        $headers = [];

        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $values[$this->columnIndex((string) $cell['r'])] = $value;
            }

            ksort($values);
            $columnCount = max(count($headers), (int) array_key_last($values));
            $values = array_map(
                fn (int $column) => $values[$column] ?? null,
                range(1, $columnCount),
            );
            if ($headers === []) {
                $headers = $values;
                continue;
            }
            $rows[] = array_combine(
                $headers,
                array_slice(array_pad($values, count($headers), null), 0, count($headers)),
            );
        }

        $zip->close();

        return $rows;
    }

    private function columnIndex(string $cellReference): int
    {
        if (preg_match('/^([A-Z]+)/i', $cellReference, $matches) !== 1) {
            throw ValidationException::withMessages(['statement' => 'The Excel file contains an invalid cell reference.']);
        }

        $column = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $column = ($column * 26) + ord($letter) - ord('A') + 1;
        }

        return $column;
    }

    private function normalize(array $row, int $line): array
    {
        $row = collect($row)->mapWithKeys(fn ($value, $key) => [strtolower(trim((string) $key)) => is_string($value) ? trim($value) : $value])->all();
        $statementId = $this->value($row, ['statement_id', 'statement id', 'transaction_id', 'transaction id', 'id']);
        $description = $this->value($row, ['description', 'details', 'narration']);
        $amount = $this->value($row, ['amount', 'transaction_amount', 'transaction amount']);
        $date = $this->value($row, ['transaction_date', 'transaction date', 'date', 'value_date']);
        $transactionType = strtolower($this->value($row, ['transaction_type', 'transaction type', 'type']) ?? 'deposit');

        if (blank($statementId) || blank($description) || blank($amount) || blank($date)) {
            throw ValidationException::withMessages(['statement' => "Missing required transaction data on row {$line}."]);
        }

        if (! in_array($transactionType, ['deposit', 'withdrawal'], true)) {
            throw ValidationException::withMessages(['statement' => "Invalid transaction type on row {$line}."]);
        }

        if (! is_numeric(str_replace(',', '', $amount))) {
            throw ValidationException::withMessages(['statement' => "Invalid amount on row {$line}."]);
        }

        try {
            $transactionDate = is_numeric($date)
                ? Carbon::create(1899, 12, 30)->addDays((int) $date)->toDateString()
                : Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['statement' => "Invalid transaction date on row {$line}."]);
        }

        return [
            'statement_id' => $statementId,
            'description' => $description,
            'reference_number' => $this->value($row, ['reference_number', 'reference number', 'reference', 'transaction_reference']),
            'amount' => str_replace(',', '', $amount),
            'transaction_date' => $transactionDate,
            'payment_method' => $this->value($row, ['payment_method', 'payment method', 'channel'])
                ?? $this->channelResolver->resolve($description),
            'transaction_type' => $transactionType,
            'status' => $transactionType === 'withdrawal' ? 'unreconciled' : 'unmatched',
        ];
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && filled($row[$key])) {
                return (string) $row[$key];
            }
        }

        return null;
    }

    private function isDuplicateEntry(array $data): bool
    {
        $query = BankLedgerEntry::query()
            ->where('transaction_type', $data['transaction_type'])
            ->whereDate('transaction_date', $data['transaction_date'])
            ->where('amount', (float) $data['amount']);

        return filled($data['reference_number'])
            ? $query->where('reference_number', $data['reference_number'])->exists()
            : $query->where('description', $data['description'])->exists();
    }
}
