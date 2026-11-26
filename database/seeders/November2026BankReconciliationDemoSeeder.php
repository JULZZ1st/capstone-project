<?php

namespace Database\Seeders;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class November2026BankReconciliationDemoSeeder extends Seeder
{
    private const MARKER = 'TEST DATA';

    public function run(): void
    {
        $targetEmail = trim((string) env('BANK_RECONCILIATION_DEMO_USER_EMAIL', 'staff@barangay.test'));
        $staff = User::query()->where('email', $targetEmail)->firstOrFail();

        if (! $staff->hasRole('staff') || $staff->status !== 'active') {
            throw new RuntimeException('The demo target must be an active Staff account.');
        }

        $workspace = $this->resolveWorkspace($staff);
        $admin = $this->workspaceAdmin($workspace, $staff);
        $donor = Donor::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            throw new RuntimeException('The selected workspace needs an active Admin account for verified and approved demo records.');
        }

        if ($donor === null) {
            throw new RuntimeException('The selected workspace needs an active donor for the verified incoming demo records.');
        }

        $account = config('bank_reconciliation.bank_accounts.0', 'LANDBANK Barangay Operating Account');
        $incoming = $this->incomingDefinitions();
        $outgoing = $this->outgoingDefinitions();
        $unmatched = $this->unmatchedDefinitions();
        $mismatch = $this->mismatchDefinition();

        app(WorkspaceContext::class)->set($workspace);

        try {
            $result = DB::transaction(function () use ($workspace, $staff, $admin, $donor, $account, $incoming, $outgoing, $unmatched, $mismatch): array {
                $period = $this->ensurePeriod($workspace, $staff, $account);
                $contributions = [];
                $disbursements = [];

                foreach ($incoming as $definition) {
                    $contributions[$definition['key']] = $this->ensureContribution($workspace, $staff, $admin, $donor, $definition);
                }

                foreach ($outgoing as $definition) {
                    $disbursements[$definition['key']] = $this->ensureDisbursement($workspace, $staff, $admin, $definition);
                }

                $entries = [];
                foreach ($incoming as $definition) {
                    $entries[] = $this->ensureBankEntry($workspace, $definition['statement_id'], [
                        'description' => $definition['description'],
                        'reference_number' => $definition['reference'],
                        'amount' => $definition['amount'],
                        'transaction_date' => $definition['date'],
                        'transaction_type' => 'deposit',
                        'payment_method' => 'cash deposit',
                        'status' => 'unmatched',
                        'notes' => self::MARKER.' — incoming matching deposit.',
                    ]);
                }

                foreach ($outgoing as $definition) {
                    $entries[] = $this->ensureBankEntry($workspace, $definition['statement_id'], [
                        'description' => 'TEST DATA — '.$definition['purpose'],
                        'reference_number' => $definition['reference'],
                        'amount' => $definition['amount'],
                        'transaction_date' => $definition['date'],
                        'transaction_type' => 'withdrawal',
                        'payment_method' => 'check',
                        'status' => 'unreconciled',
                        'notes' => self::MARKER.' — outgoing matching withdrawal.',
                    ]);
                }

                foreach ($unmatched as $definition) {
                    $entries[] = $this->ensureBankEntry($workspace, $definition['statement_id'], [
                        'description' => $definition['description'],
                        'reference_number' => $definition['reference'],
                        'amount' => $definition['amount'],
                        'transaction_date' => $definition['date'],
                        'transaction_type' => $definition['type'],
                        'payment_method' => $definition['type'] === 'deposit' ? 'cash deposit' : 'check',
                        'status' => $definition['type'] === 'deposit' ? 'unmatched' : 'unreconciled',
                        'notes' => self::MARKER.' — intentionally unmatched test transaction.',
                    ]);
                }

                $entries[] = $this->ensureBankEntry($workspace, $mismatch['statement_id'], [
                    'description' => $mismatch['description'],
                    'reference_number' => $mismatch['reference'],
                    'amount' => $mismatch['amount'],
                    'transaction_date' => $mismatch['date'],
                    'transaction_type' => 'withdrawal',
                    'payment_method' => 'check',
                    'status' => 'unreconciled',
                    'notes' => self::MARKER.' — amount mismatch test; reference matches an approved disbursement but amount differs.',
                ]);

                app(ReconciliationPeriodService::class)->recalculate($period->fresh());

                return [
                    'period' => $period->fresh(),
                    'contributions' => $contributions,
                    'disbursements' => $disbursements,
                    'entries' => $entries,
                ];
            });
        } finally {
            app(WorkspaceContext::class)->clear();
        }

        $this->command?->info('Workspace: '.$workspace->name.' (#'.$workspace->id.')');
        $this->command?->info('Period: November 2026, '.$account.' (#'.$result['period']->id.')');
        $this->command?->info('Ensured 5 verified incoming donations, 5 eligible approved disbursements, 13 bank transactions, and no matches.');

        foreach ($result['contributions'] as $contribution) {
            $this->command?->line('INCOMING TEST DATA | contribution #'.$contribution->id.' | '.$contribution->reference_number.' | PHP '.number_format((float) $contribution->amount, 2));
        }

        foreach ($result['disbursements'] as $disbursement) {
            $this->command?->line('OUTGOING TEST DATA | disbursement #'.$disbursement->id.' | '.$disbursement->supporting_reference.' | PHP '.number_format((float) $disbursement->amount, 2));
        }

        foreach ($result['entries'] as $entry) {
            $this->command?->line('BANK TEST DATA | entry #'.$entry->id.' | '.$entry->statement_id.' | '.$entry->transaction_type.' | PHP '.number_format((float) $entry->amount, 2).' | '.$entry->status);
        }
    }

    private function ensurePeriod(Workspace $workspace, User $staff, string $account): BankReconciliation
    {
        $period = BankReconciliation::query()
            ->where('workspace_id', $workspace->id)
            ->where('bank_account', $account)
            ->whereDate('period_start', '2026-11-01')
            ->whereDate('period_end', '2026-11-30')
            ->first();

        if ($period !== null) {
            $notes = strtolower((string) $period->notes);
            $isClearlyDemo = str_contains($notes, 'test')
                || str_contains($notes, 'demo')
                || str_contains($notes, 'sample');

            if (! $isClearlyDemo) {
                throw new RuntimeException('A non-demo November 2026 period already exists for this bank account; refusing to modify it.');
            }

            return $period;
        }

        return app(ReconciliationPeriodService::class)->create([
            'workspace_id' => $workspace->id,
            'bank_account' => $account,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
            'opening_balance' => 100000.00,
            'bank_ending_balance' => 144988.89,
            'prepared_by' => $staff->id,
            'notes' => self::MARKER.' — manually match records; no transactions are pre-cleared.',
        ]);
    }

    private function ensureContribution(Workspace $workspace, User $staff, User $admin, Donor $donor, array $definition): Contribution
    {
        $existing = Contribution::query()
            ->where('workspace_id', $workspace->id)
            ->where('reference_number', $definition['reference'])
            ->first();

        if ($existing !== null) {
            $this->assertDemoRecord($existing, $definition['amount'], 'amount', self::MARKER);
            $this->assertDemoRecord($existing, 'verified', 'status', self::MARKER);
            $this->assertDemoRecord($existing, 'bank_transfer', 'payment_method', self::MARKER);
            return $existing;
        }

        return Contribution::query()->create([
            'workspace_id' => $workspace->id,
            'donor_id' => $donor->id,
            'created_by' => $staff->id,
            'verified_by' => $admin->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => $definition['amount'],
            'payment_method' => 'bank_transfer',
            'reference_number' => $definition['reference'],
            'bank_name' => 'LANDBANK',
            'registered_bank_account' => config('bank_reconciliation.bank_accounts.0', 'LANDBANK Barangay Operating Account'),
            'received_at' => $definition['date'].' 10:00:00',
            'verified_at' => Carbon::parse($definition['date'])->setTime(11, 0),
            'notes' => self::MARKER.' — verified incoming donation for manual matching.',
        ]);
    }

    private function ensureDisbursement(Workspace $workspace, User $staff, User $admin, array $definition): CashDisbursement
    {
        $existing = CashDisbursement::query()
            ->where('workspace_id', $workspace->id)
            ->where('supporting_reference', $definition['reference'])
            ->first();

        if ($existing !== null) {
            $this->assertDemoRecord($existing, $definition['amount'], 'amount', self::MARKER);
            $this->assertDemoRecord($existing, 'approved', 'status', self::MARKER);
            return $existing;
        }

        return CashDisbursement::query()->create([
            'workspace_id' => $workspace->id,
            'amount' => $definition['amount'],
            'purpose' => $definition['purpose'].' — TEST DATA',
            'recipient' => $definition['recipient'],
            'payee_address' => 'TEST DATA — Barangay Demo Address',
            'requested_date' => $definition['date'],
            'supporting_reference' => $definition['reference'],
            'requested_by' => $staff->id,
            'approved_by' => $admin->id,
            'status' => 'approved',
            'payment_status' => 'not_disbursed',
            'approved_at' => Carbon::parse($definition['date'])->setTime(15, 0),
            'notes' => self::MARKER.' — approved disbursement for manual outgoing matching.',
        ]);
    }

    private function ensureBankEntry(Workspace $workspace, string $statementId, array $attributes): BankLedgerEntry
    {
        $existing = BankLedgerEntry::query()
            ->where('workspace_id', $workspace->id)
            ->where('statement_id', $statementId)
            ->first();

        if ($existing !== null) {
            $this->assertDemoRecord($existing, $attributes['amount'], 'amount', self::MARKER);
            $this->assertDemoRecord($existing, $attributes['transaction_type'], 'transaction_type', self::MARKER);
            return $existing;
        }

        return BankLedgerEntry::query()->create([
            'workspace_id' => $workspace->id,
            'statement_id' => $statementId,
            ...$attributes,
        ]);
    }

    private function assertDemoRecord(Model $record, mixed $expected, string $field, string $marker): void
    {
        $actual = $record->getAttribute($field);
        $matches = is_numeric($actual) && is_numeric($expected)
            ? abs((float) $actual - (float) $expected) < 0.005
            : (string) $actual === (string) $expected;

        if (! $matches || ! str_contains((string) ($record->notes ?? ''), $marker)) {
            throw new RuntimeException('Refusing to overwrite existing non-demo or conflicting record #'.$record->getKey().' ('.$field.').');
        }
    }

    private function resolveWorkspace(User $user): Workspace
    {
        $memberships = $user->workspaceMemberships()->where('status', 'active')->with('workspace')->get();
        if ($memberships->count() !== 1) {
            throw new RuntimeException('The demo target must have exactly one active workspace membership.');
        }

        return $memberships->firstOrFail()->workspace;
    }

    private function workspaceAdmin(Workspace $workspace, User $excluded): ?User
    {
        return User::query()
            ->where('status', 'active')
            ->where('id', '!=', $excluded->id)
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $workspace->id)->where('status', 'active'))
            ->first();
    }

    private function incomingDefinitions(): array
    {
        return [
            ['key' => '001', 'reference' => 'TEST-NOV26-DONATION-001', 'statement_id' => 'TEST-NOV26-IN-001', 'amount' => 12500.00, 'date' => '2026-11-03', 'description' => 'TEST DATA — LANDBANK donation deposit 001'],
            ['key' => '002', 'reference' => 'TEST-NOV26-DONATION-002', 'statement_id' => 'TEST-NOV26-IN-002', 'amount' => 18500.00, 'date' => '2026-11-07', 'description' => 'TEST DATA — LANDBANK donation deposit 002'],
            ['key' => '003', 'reference' => 'TEST-NOV26-DONATION-003', 'statement_id' => 'TEST-NOV26-IN-003', 'amount' => 24000.00, 'date' => '2026-11-12', 'description' => 'TEST DATA — LANDBANK donation deposit 003'],
            ['key' => '004', 'reference' => 'TEST-NOV26-DONATION-004', 'statement_id' => 'TEST-NOV26-IN-004', 'amount' => 31500.00, 'date' => '2026-11-18', 'description' => 'TEST DATA — LANDBANK donation deposit 004'],
            ['key' => '005', 'reference' => 'TEST-NOV26-DONATION-005', 'statement_id' => 'TEST-NOV26-IN-005', 'amount' => 42000.00, 'date' => '2026-11-24', 'description' => 'TEST DATA — LANDBANK donation deposit 005'],
        ];
    }

    private function outgoingDefinitions(): array
    {
        return [
            ['key' => '001', 'reference' => 'TEST-NOV26-DISBURSEMENT-001', 'statement_id' => 'TEST-NOV26-OUT-001', 'amount' => 6500.00, 'date' => '2026-11-04', 'recipient' => 'TEST DATA Supplier One', 'purpose' => 'Relief goods procurement'],
            ['key' => '002', 'reference' => 'TEST-NOV26-DISBURSEMENT-002', 'statement_id' => 'TEST-NOV26-OUT-002', 'amount' => 9800.00, 'date' => '2026-11-09', 'recipient' => 'TEST DATA Supplier Two', 'purpose' => 'Community health supplies'],
            ['key' => '003', 'reference' => 'TEST-NOV26-DISBURSEMENT-003', 'statement_id' => 'TEST-NOV26-OUT-003', 'amount' => 12750.00, 'date' => '2026-11-14', 'recipient' => 'TEST DATA Supplier Three', 'purpose' => 'Emergency response materials'],
            ['key' => '004', 'reference' => 'TEST-NOV26-DISBURSEMENT-004', 'statement_id' => 'TEST-NOV26-OUT-004', 'amount' => 18000.00, 'date' => '2026-11-20', 'recipient' => 'TEST DATA Supplier Four', 'purpose' => 'Nutrition program supplies'],
            ['key' => '005', 'reference' => 'TEST-NOV26-DISBURSEMENT-005', 'statement_id' => 'TEST-NOV26-OUT-005', 'amount' => 22500.00, 'date' => '2026-11-27', 'recipient' => 'TEST DATA Supplier Five', 'purpose' => 'Barangay equipment purchase'],
        ];
    }

    private function unmatchedDefinitions(): array
    {
        return [
            ['reference' => 'TEST-NOV26-UNMATCHED-IN', 'statement_id' => 'TEST-NOV26-IN-UNMATCHED', 'amount' => 7777.77, 'date' => '2026-11-15', 'type' => 'deposit', 'description' => 'TEST DATA — unmatched incoming LANDBANK deposit'],
            ['reference' => 'TEST-NOV26-UNMATCHED-OUT', 'statement_id' => 'TEST-NOV26-OUT-UNMATCHED', 'amount' => 8888.88, 'date' => '2026-11-22', 'type' => 'withdrawal', 'description' => 'TEST DATA — unmatched outgoing LANDBANK withdrawal'],
        ];
    }

    private function mismatchDefinition(): array
    {
        return [
            'reference' => 'TEST-NOV26-DISBURSEMENT-003',
            'statement_id' => 'TEST-NOV26-OUT-MISMATCH',
            'amount' => 12850.00,
            'date' => '2026-11-16',
            'description' => 'TEST DATA — amount mismatch against disbursement 003',
        ];
    }
}
