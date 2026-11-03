<?php

namespace Database\Seeders;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BankReconciliationIncomingFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $targetEmail = trim((string) env('BANK_RECONCILIATION_INCOMING_FIXTURE_USER_EMAIL'));

        if ($targetEmail === '') {
            throw new RuntimeException('Set BANK_RECONCILIATION_INCOMING_FIXTURE_USER_EMAIL to an existing active Staff account before seeding.');
        }

        $staff = User::query()->where('email', $targetEmail)->firstOrFail();
        if (! $staff->hasRole(['staff', 'secretary', 'treasurer']) || $staff->status !== 'active') {
            throw new RuntimeException('The target account must be an active Staff, Secretary, or Treasurer account.');
        }

        $workspace = $this->resolveWorkspace($staff);
        $donor = null;
        $created = [];
        $skipped = [];

        app(WorkspaceContext::class)->set($workspace);

        try {
            $donor = Donor::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if ($donor === null) {
                throw new RuntimeException('The target workspace has no active donor to use for the incoming fixtures.');
            }

            $deposits = BankLedgerEntry::query()
                ->where('transaction_type', 'deposit')
                ->where('status', 'unmatched')
                ->whereNull('matched_contribution_id')
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            DB::transaction(function () use ($deposits, $staff, $donor, &$created, &$skipped): void {
                foreach ($deposits as $deposit) {
                    $reference = $deposit->reference_number ?: $deposit->statement_id;
                    $receivedAt = $deposit->transaction_date->toDateString();

                    $existing = Contribution::query()
                        ->where('type', 'cash')
                        ->where('amount', $deposit->amount)
                        ->whereDate('received_at', $receivedAt)
                        ->where('reference_number', $reference)
                        ->first();

                    if ($existing !== null) {
                        $skipped[] = [
                            'reference' => $reference,
                            'contribution_id' => $existing->id,
                            'status' => $existing->status,
                        ];

                        continue;
                    }

                    $contribution = Contribution::query()->create([
                        'donor_id' => $donor->id,
                        'created_by' => $staff->id,
                        'type' => 'cash',
                        'status' => 'pending',
                        'amount' => $deposit->amount,
                        // LANDBANK cash deposits are represented as bank-transfer
                        // receipts so they remain eligible after Admin verification.
                        'payment_method' => 'bank_transfer',
                        'reference_number' => $reference,
                        'received_at' => $receivedAt,
                        'notes' => 'Incoming LANDBANK matching practice fixture. Verify before reconciliation.',
                    ]);

                    $created[] = [
                        'reference' => $reference,
                        'contribution_id' => $contribution->id,
                        'amount' => $contribution->amount,
                        'received_at' => $receivedAt,
                        'status' => $contribution->status,
                    ];
                }
            });
        } finally {
            app(WorkspaceContext::class)->clear();
        }

        $this->command?->info('Incoming fixture workspace: '.$workspace->name.' ('.$workspace->id.')');
        $this->command?->info('Created '.$this->count($created).' pending donation fixture(s); skipped '.$this->count($skipped).' existing match(es).');

        foreach ($created as $item) {
            $this->command?->line('CREATED '.$item['reference'].' | PHP '.number_format((float) $item['amount'], 2).' | '.$item['received_at'].' | pending');
        }

        foreach ($skipped as $item) {
            $this->command?->line('SKIPPED '.$item['reference'].' | contribution #'.$item['contribution_id'].' | '.$item['status']);
        }
    }

    private function resolveWorkspace(User $user): Workspace
    {
        $memberships = $user->workspaceMemberships()
            ->where('status', 'active')
            ->with('workspace')
            ->get();

        if ($memberships->count() !== 1) {
            throw new RuntimeException('The target account must have exactly one active workspace membership so the seeder cannot choose the wrong tenant.');
        }

        return $memberships->firstOrFail()->workspace;
    }

    private function count(array $items): int
    {
        return count($items);
    }
}
