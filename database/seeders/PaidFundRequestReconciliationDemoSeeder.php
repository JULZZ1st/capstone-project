<?php

namespace Database\Seeders;

use App\Models\BankReconciliation;
use App\Models\CashDisbursement;
use App\Models\CashDisbursementPayment;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use App\Services\Disbursements\ApproveDisbursement;
use App\Services\Disbursements\RecordDisbursementPayment;
use App\Services\Disbursements\RequestDisbursement;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class PaidFundRequestReconciliationDemoSeeder extends Seeder
{
    private const MARKER = 'BR-PAID-DEMO-2026-11';

    private const OUTPUT = 'test/test-demo/landbank-paid-fund-requests-demo.xlsx';

    private const TEMPLATE = 'tests/demo-data/bank-reconciliation/landbank-sample-statement.xlsx';

    private const STAFF_EMAIL = 'staff@barangay.test';

    public function run(): void
    {
        $this->guardLocalTestDatabase();

        $staff = User::query()->where('email', self::STAFF_EMAIL)->where('status', 'active')->firstOrFail();
        if (! $staff->hasRole('staff')) {
            throw new RuntimeException('The paid reconciliation demo target must be an active Staff account.');
        }

        $workspace = $this->resolveWorkspace($staff);
        $admin = $this->workspaceAdmin($workspace, $staff)
            ?? throw new RuntimeException('The test workspace needs a different active Admin for maker-checker approval.');

        app(WorkspaceContext::class)->set($workspace);

        try {
            $payments = DB::transaction(function () use ($staff, $admin, $workspace): Collection {
                $this->alignExistingDemoRecords($workspace, $staff, $admin);

                $payments = collect($this->matchedDefinitions())->map(
                    fn (array $definition) => $this->ensurePaidFundRequest($definition, $staff, $admin)
                );

                $this->ensurePeriod($workspace, $staff);

                return $payments;
            });

            $rows = $this->statementRows($payments);
            $output = $this->writeWorkbook($rows);
            $this->writeDocumentation($rows, $workspace, $staff, $admin);

            $this->command?->info("Database: {$this->databaseName()}");
            $this->command?->info("Workspace: {$workspace->name} (#{$workspace->id})");
            $this->command?->info("Requester/payment recorder: {$staff->email} (#{$staff->id})");
            $this->command?->info("Approver: {$admin->email} (#{$admin->id})");
            $this->command?->info('Ensured 3 approved and paid Fund Requests plus a November 2026 draft reconciliation period.');
            $this->command?->info('Workbook: '.$output);

            foreach ($rows as $row) {
                $label = str_contains($row['description'], 'UNMATCHED') ? 'UNMATCHED' : 'MATCH';
                $this->command?->line(sprintf(
                    '%s | %s | %s | PHP %s | %s | withdrawal',
                    $label,
                    $row['transaction_date'],
                    $row['reference_number'],
                    number_format((float) $row['amount'], 2),
                    $row['description'],
                ));
            }
        } finally {
            app(WorkspaceContext::class)->clear();
        }
    }

    private function ensurePaidFundRequest(array $definition, User $staff, User $admin)
    {
        $marker = self::MARKER.'-'.$definition['key'];
        $existing = CashDisbursement::query()->where('notes', $marker)->first();

        if ($existing !== null) {
            $payment = $existing->payment()->first();
            $valid = $existing->status === 'approved'
                && $existing->payment_status === 'disbursed'
                && $existing->voided_at === null
                && (int) $existing->requested_by === (int) $staff->id
                && (int) $existing->approved_by === (int) $admin->id
                && $payment !== null
                && $payment->payment_method === 'bank_transfer'
                && $payment->payment_date?->toDateString() === $definition['payment_date']
                && abs((float) $payment->amount - $definition['amount']) < 0.005
                && $payment->reference_number === $definition['reference'];

            if (! $valid) {
                throw new RuntimeException("Existing demo record {$marker} differs from the expected paid workflow; refusing to modify it.");
            }

            return $payment;
        }

        $request = app(RequestDisbursement::class)->handle([
            'amount' => $definition['amount'],
            'purpose' => $definition['purpose'],
            'emergency_category' => $definition['category'],
            'recipient' => $definition['recipient'],
            'requested_date' => $definition['requested_date'],
            'payee_address' => 'E2E Test Address, Barangay Demo',
            'payee_tin' => null,
            'fund_source' => config('disbursements.fund_sources.0', 'General Fund'),
            'obr_number' => 'OBR-'.$definition['key'],
            'notes' => $marker,
        ], $staff);

        $request->forceFill([
            'treasurer_certified_by' => $staff->id,
            'treasurer_certified_at' => now(),
            'treasurer_certification_notes' => 'Demo physical certification recorded before Admin review.',
        ])->save();

        SecurityActivityEvent::query()->create([
            'user_id' => $request->requested_by,
            'actor_id' => $staff->id,
            'action' => 'disbursement_treasurer_certified',
            'summary' => "{$staff->name} recorded demo Treasurer certification for fund request #{$request->id}.",
            'metadata' => ['disbursement_id' => $request->id, 'demo_marker' => $marker],
            'occurred_at' => now(),
        ]);

        $request = app(ApproveDisbursement::class)->handle($request, $admin);

        return app(RecordDisbursementPayment::class)->handle($request, [
            'payment_date' => $definition['payment_date'],
            'amount' => $definition['amount'],
            'payment_method' => 'bank_transfer',
            'reference_number' => $definition['reference'],
        ], $staff);
    }

    private function ensurePeriod(Workspace $workspace, User $staff): BankReconciliation
    {
        $period = BankReconciliation::query()->withoutGlobalScopes()
            ->where('notes', self::MARKER.'-PERIOD')
            ->first();

        if ($period !== null) {
            if ((int) $period->workspace_id !== (int) $workspace->id) {
                if (! $period->isDraft() || $period->discrepancies()->exists()) {
                    throw new RuntimeException('The obsolete paid-demo period is no longer empty and cannot be removed safely.');
                }
                $period->delete();
                $period = null;
            } elseif ($period->period_start?->toDateString() !== '2026-11-01'
                || $period->period_end?->toDateString() !== '2026-11-30'
                || ! $period->isDraft()) {
                throw new RuntimeException('The existing paid-demo reconciliation period was changed; refusing to overwrite it.');
            }
            if ($period !== null) {
                return $period;
            }
        }

        $existingNovemberPeriod = BankReconciliation::query()
            ->whereDate('period_start', '<=', '2026-11-30')
            ->whereDate('period_end', '>=', '2026-11-01')
            ->first();
        if ($existingNovemberPeriod !== null) {
            if (! $existingNovemberPeriod->isDraft()) {
                throw new RuntimeException('The target workspace November 2026 reconciliation period is not editable.');
            }

            return $existingNovemberPeriod;
        }

        return app(ReconciliationPeriodService::class)->create([
            'workspace_id' => $workspace->id,
            'bank_account' => config('bank_reconciliation.bank_accounts.0', 'LANDBANK Barangay Operating Account'),
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
            'opening_balance' => 100000.00,
            'bank_ending_balance' => 67491.67,
            'prepared_by' => $staff->id,
            'notes' => self::MARKER.'-PERIOD',
        ]);
    }

    private function statementRows(Collection $payments): array
    {
        $matched = $payments->values()->map(function ($payment, int $index): array {
            $disbursement = $payment->disbursement;

            return [
                'statement_id' => sprintf('BR-DEMO-LB-202611-%03d', $index + 1),
                'description' => 'FUND REQUEST PAYMENT - '.$disbursement->recipient,
                'reference_number' => $payment->reference_number,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'transaction_date' => $payment->payment_date->toDateString(),
                'payment_method' => 'bank_transfer',
                'transaction_type' => 'withdrawal',
            ];
        });

        return $matched->concat($this->unmatchedDefinitions())->all();
    }

    private function writeWorkbook(array $rows): string
    {
        $template = base_path(self::TEMPLATE);
        $output = base_path(self::OUTPUT);

        if (! is_file($template)) {
            throw new RuntimeException('The LANDBANK statement template is missing: '.$template);
        }

        File::ensureDirectoryExists(dirname($output));
        if (! copy($template, $output)) {
            throw new RuntimeException('Unable to copy the LANDBANK statement template.');
        }

        $headers = ['statement_id', 'description', 'reference_number', 'amount', 'transaction_date', 'payment_method', 'transaction_type'];
        $sheetRows = [$headers, ...array_map(fn (array $row): array => array_values($row), $rows)];
        $xmlRows = [];

        foreach ($sheetRows as $rowIndex => $values) {
            $excelRow = $rowIndex + 1;
            $cells = [];
            foreach ($values as $columnIndex => $value) {
                $column = chr(ord('A') + $columnIndex);
                $reference = $column.$excelRow;
                if ($columnIndex === 3 && $rowIndex > 0) {
                    $cells[] = '<c r="'.$reference.'" s="2"><v>'.(float) $value.'</v></c>';
                } else {
                    $style = $rowIndex === 0 ? ' s="3"' : ' s="3"';
                    $cells[] = '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t>'.$this->xml((string) $value).'</t></is></c>';
                }
            }
            $xmlRows[] = '<row r="'.$excelRow.'">'.implode('', $cells).'</row>';
        }

        $lastRow = count($sheetRows);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:G'.$lastRow.'"/><sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';

        $zip = new ZipArchive;
        if ($zip->open($output) !== true) {
            throw new RuntimeException('Unable to open the generated LANDBANK workbook.');
        }

        try {
            $zip->deleteName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        } finally {
            $zip->close();
        }

        return $output;
    }

    private function writeDocumentation(array $rows, Workspace $workspace, User $staff, User $admin): void
    {
        $lines = [
            '# Paid Fund Request reconciliation demo',
            '',
            '- Database: `'.$this->databaseName().'`',
            '- Workspace: `'.$workspace->name.'` (#'.$workspace->id.')',
            '- Staff requester/payment recorder: `'.$staff->email.'`',
            '- Admin approver: `'.$admin->email.'`',
            '- Workbook: `landbank-paid-fund-requests-demo.xlsx`',
            '',
            '| Expected | Date | Reference | Amount | Direction | Description |',
            '|---|---|---|---:|---|---|',
        ];

        foreach ($rows as $row) {
            $expected = str_contains($row['description'], 'UNMATCHED') ? 'Discrepancy' : 'Fund Request match';
            $lines[] = sprintf('| %s | %s | %s | PHP %s | withdrawal | %s |',
                $expected,
                $row['transaction_date'],
                $row['reference_number'],
                number_format((float) $row['amount'], 2),
                $row['description'],
            );
        }

        File::put(base_path('test/test-demo/README.md'), implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function guardLocalTestDatabase(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('This demo seeder refuses to run in production.');
        }

        $expected = trim((string) env('BANK_RECONCILIATION_PAID_DEMO_DATABASE'));
        $actual = $this->databaseName();
        if ($expected === '' || ! hash_equals($actual, $expected)) {
            throw new RuntimeException("Set BANK_RECONCILIATION_PAID_DEMO_DATABASE={$actual} to confirm the exact local test database.");
        }

        $connection = config('database.default');
        if ($connection === 'mysql') {
            $host = strtolower((string) config('database.connections.mysql.host'));
            if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new RuntimeException('This demo seeder only allows a local MySQL host.');
            }
        } elseif ($connection !== 'sqlite') {
            throw new RuntimeException('This demo seeder only supports local MySQL or SQLite test databases.');
        }
    }

    private function databaseName(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }

    private function resolveWorkspace(User $user): Workspace
    {
        $memberships = $user->workspaceMemberships()->where('status', 'active')->with('workspace')->get();
        if ($memberships->count() !== 1) {
            throw new RuntimeException('The target Staff account must have exactly one active workspace membership.');
        }

        return $memberships->firstOrFail()->workspace;
    }

    private function workspaceAdmin(Workspace $workspace, User $excluded): ?User
    {
        return User::query()
            ->where('status', 'active')
            ->whereKeyNot($excluded->id)
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->whereHas('workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active'))
            ->orderByRaw('email = ? desc', ['admin@barangay.test'])
            ->orderBy('id')
            ->first();
    }

    private function alignExistingDemoRecords(Workspace $workspace, User $staff, User $admin): void
    {
        CashDisbursement::query()->withoutGlobalScopes()
            ->where('notes', 'like', self::MARKER.'-%')
            ->where('notes', '!=', self::MARKER.'-PERIOD')
            ->get()
            ->each(function (CashDisbursement $request) use ($workspace, $staff, $admin): void {
                if ($request->voided_at !== null || $request->status !== 'approved' || $request->payment_status !== 'disbursed') {
                    throw new RuntimeException("Demo Fund Request #{$request->id} is no longer safe to relocate.");
                }

                $payment = CashDisbursementPayment::query()->withoutGlobalScopes()
                    ->where('cash_disbursement_id', $request->id)
                    ->first();
                if ($payment === null) {
                    throw new RuntimeException("Demo Fund Request #{$request->id} is missing its payment.");
                }

                $request->forceFill([
                    'workspace_id' => $workspace->id,
                    'requested_by' => $staff->id,
                    'treasurer_certified_by' => $staff->id,
                    'approved_by' => $admin->id,
                ])->save();

                $payment->forceFill([
                    'workspace_id' => $workspace->id,
                    'released_by' => $staff->id,
                ])->save();
            });

        $sequence = DB::table('dv_number_sequences')
            ->where('workspace_id', $workspace->id)
            ->where('period', '2026-11');
        if ($sequence->exists()) {
            $sequence->where('last_number', '<', 3)->update(['last_number' => 3, 'updated_at' => now()]);
        } else {
            DB::table('dv_number_sequences')->insert([
                'workspace_id' => $workspace->id,
                'period' => '2026-11',
                'last_number' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function matchedDefinitions(): array
    {
        return [
            ['key' => '001', 'amount' => 6500.00, 'requested_date' => '2026-11-02', 'payment_date' => '2026-11-05', 'reference' => 'BR-DEMO-PAY-202611-001', 'category' => 'Typhoon', 'purpose' => 'Demo relief goods procurement', 'recipient' => 'Demo Relief Goods Supplier'],
            ['key' => '002', 'amount' => 9800.00, 'requested_date' => '2026-11-08', 'payment_date' => '2026-11-12', 'reference' => 'BR-DEMO-PAY-202611-002', 'category' => 'Health Outbreak', 'purpose' => 'Demo community health supplies', 'recipient' => 'Demo Community Health Supplier'],
            ['key' => '003', 'amount' => 12750.00, 'requested_date' => '2026-11-15', 'payment_date' => '2026-11-19', 'reference' => 'BR-DEMO-PAY-202611-003', 'category' => 'Flood', 'purpose' => 'Demo emergency response materials', 'recipient' => 'Demo Emergency Materials Supplier'],
        ];
    }

    private function unmatchedDefinitions(): array
    {
        return [
            ['statement_id' => 'BR-DEMO-LB-202611-004', 'description' => 'UNMATCHED DEMO - LANDBANK SERVICE CHARGE', 'reference_number' => 'BR-DEMO-UNMATCHED-FEE-001', 'amount' => '125.00', 'transaction_date' => '2026-11-10', 'payment_method' => 'bank_charge', 'transaction_type' => 'withdrawal'],
            ['statement_id' => 'BR-DEMO-LB-202611-005', 'description' => 'UNMATCHED DEMO - UNIDENTIFIED OUTGOING TRANSFER', 'reference_number' => 'BR-DEMO-UNMATCHED-OUT-001', 'amount' => '3333.33', 'transaction_date' => '2026-11-24', 'payment_method' => 'bank_transfer', 'transaction_type' => 'withdrawal'],
        ];
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
