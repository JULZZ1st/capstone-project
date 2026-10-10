<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
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
    public function __construct(private readonly LedgerChannelResolver $channelResolver)
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
        if (blank($data['reference_number'])) {
            return false;
        }

        return BankLedgerEntry::query()
            ->where('transaction_type', $data['transaction_type'])
            ->where('reference_number', $data['reference_number'])
            ->whereDate('transaction_date', $data['transaction_date'])
            ->where('amount', (float) $data['amount'])
            ->exists();
    }
}
