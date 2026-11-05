<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class ImportBankStatement
{
    public function __construct(
        private readonly LedgerChannelResolver $channelResolver,
        private readonly ReconciliationPeriodGuard $periodGuard,
        private readonly ReconciliationPeriodService $periods,
    ) {}

    public function handle(UploadedFile $file, ?User $user = null): int
    {
        $rows = $this->rows($file);

        if ($rows === []) {
            throw ValidationException::withMessages(['statement' => 'The bank statement contains no data rows.']);
        }

        $created = 0;

        DB::transaction(function () use ($rows, &$created, $user) {
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
                        'statement' => 'A matching bank entry already exists on row '.($index + 2).'.',
                    ]);
                }

                BankLedgerEntry::query()->create($data);
                $created++;
            }

            BankReconciliation::query()
                ->whereIn('status', ['draft', 'ready_for_review'])
                ->get()
                ->each(fn (BankReconciliation $period) => $this->periods->recalculate($period));

            if ($user !== null) {
                SecurityActivityEvent::query()->create([
                    'actor_id' => $user->id,
                    'action' => 'bank_statement_imported',
                    'summary' => "{$user->name} imported {$created} bank transaction(s) for reconciliation.",
                    'metadata' => ['imported_count' => $created],
                    'occurred_at' => now(),
                ]);
            }
        });

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

        try {
            $rawHeaders = fgetcsv($handle);
            if ($rawHeaders === false) {
                return [];
            }
            $headers = $this->headers($rawHeaders);
            $rows = [];

            while (($values = fgetcsv($handle)) !== false) {
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $rows[] = $this->combineRow($headers, $values);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function xlsxRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['statement' => 'The Excel file could not be opened.']);
        }

        try {
            $sharedStrings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach ($this->xmlDocument($xml)->si as $string) {
                    $sharedStrings[] = $this->xmlText($string);
                }
            }

            $sheet = $this->xmlDocument($zip->getFromName('xl/worksheets/sheet1.xml'));
            if ($sheet->getName() !== 'worksheet' || ! isset($sheet->sheetData)) {
                throw ValidationException::withMessages(['statement' => 'The Excel file does not contain valid worksheet data.']);
            }
            $rows = [];
            $headers = null;

            foreach ($sheet->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $value = match ((string) $cell['t']) {
                        's' => $sharedStrings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->xmlText($cell->is),
                        default => (string) $cell->v,
                    };
                    $values[$this->columnIndex((string) $cell['r'])] = $value;
                }

                $nonempty = array_filter($values, fn ($value) => trim((string) $value) !== '');
                if ($nonempty === []) {
                    continue;
                }
                $columnCount = max(count($headers ?? []), max(array_keys($nonempty)));
                $values = array_map(fn (int $column) => $values[$column] ?? null, range(1, $columnCount));

                if ($headers === null) {
                    $headers = $this->headers($values);

                    continue;
                }
                $rows[] = $this->combineRow($headers, $values);
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function headers(array $headers): array
    {
        $headers = array_map(
            fn ($header) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header))),
            $headers,
        );
        if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
            throw ValidationException::withMessages(['statement' => 'Statement column headers must be nonempty and unique.']);
        }

        return $headers;
    }

    private function combineRow(array $headers, array $values): array
    {
        if (count($values) > count($headers)) {
            throw ValidationException::withMessages(['statement' => 'A statement row has more values than its column headers.']);
        }

        return array_combine($headers, array_pad($values, count($headers), null));
    }

    private function xmlDocument(string|false $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = $xml === false ? false : simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            if ($document === false) {
                throw ValidationException::withMessages(['statement' => 'The Excel file contains a missing or invalid worksheet or text document.']);
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function xmlText(SimpleXMLElement $element): string
    {
        return implode('', array_map('strval', $element->xpath(
            './*[local-name()="t"] | ./*[local-name()="r"]/*[local-name()="t"]',
        )));
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

        $amount = str_replace(',', '', $amount);
        if (Validator::make(['amount' => $amount], [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ])->fails()) {
            throw ValidationException::withMessages(['statement' => "Invalid amount on row {$line}."]);
        }

        try {
            if (is_numeric($date)) {
                $excelSerial = (float) $date;
                $wholeDays = (int) floor($excelSerial);
                $fractionalSeconds = (int) round(($excelSerial - $wholeDays) * 86400);
                $transactionDate = Carbon::create(1899, 12, 30)
                    ->addDays($wholeDays)
                    ->addSeconds($fractionalSeconds)
                    ->toDateString();
            } else {
                $transactionDate = Carbon::parse($date)->toDateString();
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['statement' => "Invalid transaction date on row {$line}."]);
        }

        return [
            'statement_id' => $statementId,
            'description' => $description,
            'reference_number' => $this->value($row, ['reference_number', 'reference number', 'reference', 'transaction_reference']),
            'amount' => $amount,
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
