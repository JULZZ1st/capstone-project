<?php

namespace Database\Seeders;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BankReconciliationOutgoingFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $fixturePath = base_path('tests/demo-data/bank-reconciliation/outgoing-fund-requests.json');
        $definitions = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
        $targetEmail = trim((string) env('BANK_RECONCILIATION_FIXTURE_USER_EMAIL'));

        if ($targetEmail === '') {
            throw new RuntimeException('Set BANK_RECONCILIATION_FIXTURE_USER_EMAIL to an existing active Admin or Staff account before seeding.');
        }

        $requester = User::query()->where('email', $targetEmail)->firstOrFail();
        $workspace = $this->resolveWorkspace($requester);
        $admin = $this->workspaceAdmin($workspace, $requester);

        if ($admin === null) {
            throw new RuntimeException('The selected workspace needs a different active Admin account for maker-checker approvals.');
        }

        if ($requester->roleName() === 'admin') {
            $requester = $this->workspaceStaff($workspace, $admin);
            if ($requester === null) {
                throw new RuntimeException('An Admin target requires a different active Staff account to request the sample fund requests.');
            }
        }

        app(WorkspaceContext::class)->set($workspace);

        try {
            $existingReferences = CashDisbursement::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('supporting_reference', collect($definitions)
                    ->map(fn (array $definition): string => 'DEMO-FUND-REQUEST-'.$definition['key'])
                    ->all())
                ->pluck('supporting_reference')
                ->flip();

            $missingApprovedCount = collect($definitions)
                ->filter(fn (array $definition): bool => $definition['status'] === 'approved')
                ->reject(fn (array $definition): bool => $existingReferences->has('DEMO-FUND-REQUEST-'.$definition['key']))
                ->count();

            $withdrawalAmounts = $missingApprovedCount > 0
                ? BankLedgerEntry::query()
                    ->where('transaction_type', 'withdrawal')
                    ->where('status', 'unreconciled')
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->pluck('amount')
                    ->map(fn ($amount): float => (float) $amount)
                    ->values()
                : collect();

            $approvedIndex = 0;
            foreach ($definitions as &$definition) {
                if ($existingReferences->has('DEMO-FUND-REQUEST-'.$definition['key'])) {
                    continue;
                }

                if ($definition['status'] === 'approved') {
                    if (! $withdrawalAmounts->has($approvedIndex)) {
                        throw new RuntimeException('There are not enough unreconciled LANDBANK withdrawals in the selected workspace to safely prepare every approved sample request.');
                    }
                    $definition['amount'] = $withdrawalAmounts[$approvedIndex++];
                }
            }
            unset($definition);

            DB::transaction(function () use ($workspace, $admin, $requester, $definitions): void {

                foreach ($definitions as $definition) {
                    $reference = 'DEMO-FUND-REQUEST-'.$definition['key'];
                    $approved = $definition['status'] === 'approved';
                    $rejected = $definition['status'] === 'rejected';

                    CashDisbursement::query()->firstOrCreate(
                        ['workspace_id' => $workspace->id, 'supporting_reference' => $reference],
                        [
                            'workspace_id' => $workspace->id,
                            'amount' => $definition['amount'],
                            'purpose' => $definition['purpose'],
                            'recipient' => $definition['recipient'],
                            'requested_date' => now()->subDays(10 - (int) $definition['key'])->toDateString(),
                            'notes' => 'Sample fund request for outgoing bank matching practice.',
                            'supporting_reference' => $reference,
                            'requested_by' => $requester->id,
                            'approved_by' => $approved ? $admin->id : null,
                            'status' => $definition['status'],
                            'approved_at' => $approved ? now()->subDays(5) : null,
                            'rejected_at' => $rejected ? now()->subDays(2) : null,
                            'rejection_reason' => $rejected ? 'The request was deferred because the current allocation was insufficient.' : null,
                            'voided_at' => null,
                            'voided_by' => null,
                            'void_reason' => null,
                        ],
                    );
                }
            });
        } finally {
            app(WorkspaceContext::class)->clear();
        }

        $this->command?->info('Ensured 10 workspace-scoped outgoing fund requests; existing records were preserved and all demo requests remain unchanged.');
    }

    private function resolveWorkspace(User $user): Workspace
    {
        $memberships = $user->workspaceMemberships()->where('status', 'active')->with('workspace')->get();

        if ($memberships->count() !== 1) {
            throw new RuntimeException('The target account must have exactly one active workspace membership so the seeder cannot choose the wrong tenant.');
        }

        return $memberships->firstOrFail()->workspace;
    }

    private function workspaceAdmin(Workspace $workspace, User $requester): ?User
    {
        return $this->workspaceUsers($workspace, 'admin')->first(fn (User $user): bool => $user->isNot($requester));
    }

    private function workspaceStaff(Workspace $workspace, User $excluded): ?User
    {
        return $this->workspaceUsers($workspace, ['staff', 'secretary', 'treasurer'])->first(fn (User $user): bool => $user->isNot($excluded));
    }

    private function workspaceUsers(Workspace $workspace, string|array $role)
    {
        return User::query()
            ->whereHas('role', fn ($query) => is_array($role) ? $query->whereIn('name', $role) : $query->where('name', $role))
            ->whereHas('workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active'))
            ->get();
    }
}
