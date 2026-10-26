<?php

namespace Database\Seeders;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\BankWithdrawalDisbursementMatch;
use App\Models\Campaign;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\ContributionItem;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventorySubcategory;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleBarangayDataSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->where('slug', 'legacy')->firstOrFail();
        $admin = User::query()->where('email', 'gina@gmail.com')->firstOrFail();
        $staff = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'staff'))
            ->whereHas('workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active'))
            ->first() ?? $admin;

        app(WorkspaceContext::class)->set($workspace);

        DB::transaction(function () use ($workspace, $admin, $staff): void {
            $campaigns = $this->seedCampaigns($workspace->id);
            $donors = $this->seedDonors($workspace->id);
            $contributions = $this->seedContributions($workspace->id, $admin->id, $campaigns, $donors);
            $disbursements = $this->seedDisbursements($workspace->id, $admin->id, $staff->id);
            $this->seedInventory($workspace->id, $admin->id, $donors, $contributions);
            $this->seedBankLedger($workspace->id, $admin->id, $contributions, $disbursements);
            $this->seedReconciliationPeriods($workspace->id, $admin->id);
        });

        app(WorkspaceContext::class)->clear();
    }

    private function seedCampaigns(int $workspaceId): array
    {
        $definitions = [
            ['slug' => 'sample-ayuda-kabuhayan-2026', 'name' => 'Ayuda Kabuhayan 2026', 'description' => 'Community livelihood assistance for qualified barangay families.'],
            ['slug' => 'sample-typhoon-relief-2026', 'name' => 'Bagyo Relief Drive 2026', 'description' => 'Emergency relief support for residents affected by severe weather.'],
        ];

        $campaigns = [];
        foreach ($definitions as $definition) {
            $campaigns[$definition['slug']] = Campaign::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'slug' => $definition['slug']],
                [...$definition, 'workspace_id' => $workspaceId, 'status' => 'active', 'starts_at' => now()->subDays(45), 'ends_at' => now()->addDays(45)],
            );
        }

        return $campaigns;
    }

    private function seedDonors(int $workspaceId): array
    {
        $definitions = [
            ['key' => '001', 'first_name' => 'Maria', 'last_name' => 'Santos', 'barangay' => 'Poblacion', 'city' => 'Cebu City', 'phone' => '09171234001', 'email' => 'maria.santos.sample@example.test', 'type' => 'individual'],
            ['key' => '002', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'barangay' => 'Lahug', 'city' => 'Cebu City', 'phone' => '09171234002', 'email' => 'juan.delacruz.sample@example.test', 'type' => 'individual'],
            ['key' => '003', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'barangay' => 'Mabolo', 'city' => 'Cebu City', 'phone' => '09171234003', 'email' => 'ana.reyes.sample@example.test', 'type' => 'individual'],
            ['key' => '004', 'first_name' => 'Ramon', 'last_name' => 'Garcia', 'barangay' => 'Guadalupe', 'city' => 'Cebu City', 'phone' => '09171234004', 'email' => 'ramon.garcia.sample@example.test', 'type' => 'individual'],
            ['key' => '005', 'first_name' => 'Liza', 'last_name' => 'Navarro', 'barangay' => 'Talamban', 'city' => 'Cebu City', 'phone' => '09171234005', 'email' => 'liza.navarro.sample@example.test', 'type' => 'individual'],
            ['key' => '006', 'first_name' => null, 'last_name' => null, 'barangay' => 'Banilad', 'city' => 'Cebu City', 'phone' => '09171234006', 'email' => 'donations@samplefoundation.example.test', 'type' => 'organization', 'organization' => 'Cebu Bayanihan Foundation'],
            ['key' => '007', 'first_name' => null, 'last_name' => null, 'barangay' => 'Kasambagan', 'city' => 'Cebu City', 'phone' => '09171234007', 'email' => 'csr@samplefoods.example.test', 'type' => 'organization', 'organization' => 'Bayanihan Foods Corporation'],
            ['key' => '008', 'first_name' => 'Jose', 'last_name' => 'Mendoza', 'barangay' => 'Tisa', 'city' => 'Cebu City', 'phone' => '09171234008', 'email' => 'jose.mendoza.sample@example.test', 'type' => 'individual'],
            ['key' => '009', 'first_name' => 'Carmen', 'last_name' => 'Villanueva', 'barangay' => 'Pardo', 'city' => 'Cebu City', 'phone' => '09171234009', 'email' => 'carmen.villanueva.sample@example.test', 'type' => 'individual'],
            ['key' => '010', 'first_name' => 'Pedro', 'last_name' => 'Aquino', 'barangay' => 'Sambag', 'city' => 'Cebu City', 'phone' => '09171234010', 'email' => 'pedro.aquino.sample@example.test', 'type' => 'individual'],
        ];

        $donors = [];
        foreach ($definitions as $definition) {
            $fullName = $definition['type'] === 'organization'
                ? $definition['organization']
                : trim($definition['first_name'].' '.$definition['last_name']);

            $donors[$definition['key']] = Donor::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'email' => $definition['email']],
                [
                    'workspace_id' => $workspaceId,
                    'donor_type' => $definition['type'],
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'full_name' => $fullName,
                    'contact_person' => $definition['type'] === 'organization' ? 'Barangay Partnerships Desk' : null,
                    'address' => $definition['barangay'].', '.$definition['city'].', Cebu',
                    'barangay' => $definition['barangay'],
                    'city_municipality' => $definition['city'],
                    'province' => 'Cebu',
                    'barangay_area' => $definition['barangay'],
                    'contact_number' => $definition['phone'],
                    'preferred_contact_method' => 'mobile',
                    'status' => $definition['key'] === '010' ? 'inactive' : 'active',
                    'notes' => 'Sample record for smoke testing; not a real donor.',
                ],
            );
        }

        return $donors;
    }

    private function seedContributions(int $workspaceId, int $adminId, array $campaigns, array $donors): array
    {
        $definitions = [
            ['key' => '001', 'donor' => '001', 'type' => 'cash', 'status' => 'verified', 'amount' => 2500, 'method' => 'cash', 'campaign' => 'sample-ayuda-kabuhayan-2026', 'note' => 'Cash donation received during the barangay livelihood orientation.'],
            ['key' => '002', 'donor' => '002', 'type' => 'cash', 'status' => 'verified', 'amount' => 12000, 'method' => 'bank_transfer', 'campaign' => 'sample-ayuda-kabuhayan-2026', 'note' => 'Bank transfer from a local resident association.'],
            ['key' => '003', 'donor' => '003', 'type' => 'cash', 'status' => 'verified', 'amount' => 7500, 'method' => 'gcash', 'campaign' => 'sample-typhoon-relief-2026', 'note' => 'GCash relief contribution.'],
            ['key' => '004', 'donor' => '004', 'type' => 'cash', 'status' => 'pending', 'amount' => 4000, 'method' => 'cash', 'campaign' => 'sample-typhoon-relief-2026', 'note' => 'Awaiting treasurer verification.'],
            ['key' => '005', 'donor' => '005', 'type' => 'cash', 'status' => 'verified', 'amount' => 18500, 'method' => 'bank_transfer', 'campaign' => 'sample-ayuda-kabuhayan-2026', 'note' => 'Corporate livelihood assistance transfer.'],
            ['key' => '006', 'donor' => '006', 'type' => 'in_kind', 'status' => 'received', 'amount' => null, 'method' => null, 'campaign' => 'sample-typhoon-relief-2026', 'note' => 'Rice and canned goods for family relief packs.'],
            ['key' => '007', 'donor' => '007', 'type' => 'in_kind', 'status' => 'distributed', 'amount' => null, 'method' => null, 'campaign' => 'sample-typhoon-relief-2026', 'note' => 'Hygiene kits distributed to affected households.'],
            ['key' => '008', 'donor' => '008', 'type' => 'cash', 'status' => 'cancelled', 'amount' => 3000, 'method' => 'cash', 'campaign' => 'sample-ayuda-kabuhayan-2026', 'note' => 'Cancelled after duplicate pledge confirmation.'],
            ['key' => '009', 'donor' => '009', 'type' => 'cash', 'status' => 'verified', 'amount' => 9800, 'method' => 'maya', 'campaign' => 'sample-typhoon-relief-2026', 'note' => 'Maya donation for emergency supplies.'],
            ['key' => '010', 'donor' => '010', 'type' => 'cash', 'status' => 'received', 'amount' => 6500, 'method' => 'cash', 'campaign' => 'sample-ayuda-kabuhayan-2026', 'note' => 'Cash received and queued for verification.'],
        ];

        $contributions = [];
        foreach ($definitions as $definition) {
            $reference = 'SAMPLE-DON-'.$definition['key'];
            $contributions[$definition['key']] = Contribution::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'reference_number' => $reference],
                [
                    'workspace_id' => $workspaceId,
                    'donor_id' => $donors[$definition['donor']]->id,
                    'campaign_id' => $campaigns[$definition['campaign']]->id,
                    'created_by' => $adminId,
                    'verified_by' => in_array($definition['status'], ['verified', 'distributed'], true) ? $adminId : null,
                    'type' => $definition['type'],
                    'status' => $definition['status'],
                    'amount' => $definition['amount'],
                    'payment_method' => $definition['method'],
                    'reference_number' => $reference,
                    'notes' => $definition['note'],
                    'received_at' => now()->subDays(12 - (int) $definition['key']),
                    'verified_at' => in_array($definition['status'], ['verified', 'distributed'], true) ? now()->subDays(10 - (int) $definition['key']) : null,
                ],
            );
        }

        $items = [
            ['contribution' => '006', 'name' => 'Premium Rice', 'category' => 'food', 'subcategory' => 'rice_grains', 'quantity' => 50, 'unit' => 'sack', 'condition' => 'new', 'value' => 22500],
            ['contribution' => '006', 'name' => 'Canned Sardines', 'category' => 'food', 'subcategory' => 'canned_goods', 'quantity' => 240, 'unit' => 'can', 'condition' => 'new', 'value' => 16800],
            ['contribution' => '007', 'name' => 'Family Hygiene Kit', 'category' => 'hygiene_supplies', 'subcategory' => 'cleaning_hygiene', 'quantity' => 120, 'unit' => 'pack', 'condition' => 'new', 'value' => 30000],
            ['contribution' => '007', 'name' => 'Reusable Raincoat', 'category' => 'clothing', 'subcategory' => 'jackets', 'quantity' => 80, 'unit' => 'piece', 'condition' => 'new', 'value' => 20000],
        ];

        foreach ($items as $item) {
            ContributionItem::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'contribution_id' => $contributions[$item['contribution']]->id, 'name' => $item['name']],
                [
                    'workspace_id' => $workspaceId,
                    'contribution_id' => $contributions[$item['contribution']]->id,
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'subcategory' => $item['subcategory'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'condition' => $item['condition'],
                    'estimated_value' => $item['value'],
                    'notes' => 'Sample in-kind contribution item.',
                ],
            );
        }

        return $contributions;
    }

    private function seedDisbursements(int $workspaceId, int $adminId, int $staffId): array
    {
        $definitions = [
            ['key' => '001', 'amount' => 18000, 'purpose' => 'Livelihood starter kits for 12 qualified families', 'recipient' => 'Poblacion Livelihood Association', 'status' => 'approved'],
            ['key' => '002', 'amount' => 9500, 'purpose' => 'Emergency food packs for senior citizens', 'recipient' => 'Barangay Senior Citizens Office', 'status' => 'approved'],
            ['key' => '003', 'amount' => 12400, 'purpose' => 'Tarpaulins and repair materials after heavy rain', 'recipient' => 'Barangay Disaster Risk Reduction Team', 'status' => 'approved'],
            ['key' => '004', 'amount' => 7200, 'purpose' => 'First-aid and over-the-counter medicines', 'recipient' => 'Barangay Health Station', 'status' => 'approved'],
            ['key' => '005', 'amount' => 6400, 'purpose' => 'School supplies for the community learning hub', 'recipient' => 'Poblacion Learning Hub', 'status' => 'pending'],
            ['key' => '006', 'amount' => 5800, 'purpose' => 'Additional sound system rental for assembly', 'recipient' => 'Barangay Events Committee', 'status' => 'rejected'],
            ['key' => '007', 'amount' => 3000, 'purpose' => 'Replacement storage labels and bins', 'recipient' => 'Barangay Supply Office', 'status' => 'voided'],
            ['key' => '008', 'amount' => 4100, 'purpose' => 'Community garden seedling procurement', 'recipient' => 'Urban Gardening Volunteers', 'status' => 'pending'],
        ];

        $disbursements = [];
        foreach ($definitions as $definition) {
            $reference = 'SAMPLE-FUND-'.$definition['key'];
            $approved = $definition['status'] === 'approved';
            $rejected = $definition['status'] === 'rejected';
            $voided = $definition['status'] === 'voided';
            $disbursements[$definition['key']] = CashDisbursement::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'supporting_reference' => $reference],
                [
                    'workspace_id' => $workspaceId,
                    'amount' => $definition['amount'],
                    'purpose' => $definition['purpose'],
                    'recipient' => $definition['recipient'],
                    'requested_date' => now()->subDays(9 - (int) $definition['key']),
                    'notes' => 'Sample fund request for workflow testing.',
                    'supporting_reference' => $reference,
                    'requested_by' => $staffId,
                    'approved_by' => $approved ? $adminId : null,
                    'status' => $definition['status'],
                    'approved_at' => $approved ? now()->subDays(6 - (int) $definition['key']) : null,
                    'rejected_at' => $rejected ? now()->subDays(3) : null,
                    'rejection_reason' => $rejected ? 'Request exceeded the current event allocation.' : null,
                    'voided_at' => $voided ? now()->subDays(2) : null,
                    'voided_by' => $voided ? $adminId : null,
                    'void_reason' => $voided ? 'Sample request voided after revised procurement plan.' : null,
                ],
            );
        }

        return $disbursements;
    }

    private function seedInventory(int $workspaceId, int $adminId, array $donors, array $contributions): void
    {
        $definitions = [
            ['key' => '001', 'name' => 'Premium Rice 25kg', 'category' => 'food', 'subcategory' => 'rice_grains', 'unit' => 'sack', 'quantity' => 45, 'reserve' => 20, 'value' => 1125, 'status' => 'active', 'location' => 'Main Storage A', 'expiry' => null],
            ['key' => '002', 'name' => 'Canned Sardines', 'category' => 'food', 'subcategory' => 'canned_goods', 'unit' => 'can', 'quantity' => 180, 'reserve' => 100, 'value' => 70, 'status' => 'active', 'location' => 'Main Storage B', 'expiry' => null],
            ['key' => '003', 'name' => 'Family Hygiene Kit', 'category' => 'hygiene_supplies', 'subcategory' => 'cleaning_hygiene', 'unit' => 'pack', 'quantity' => 35, 'reserve' => 40, 'value' => 250, 'status' => 'active', 'location' => 'Relief Room', 'expiry' => null],
            ['key' => '004', 'name' => 'First-Aid Kit', 'category' => 'medical_supplies', 'subcategory' => 'first_aid', 'unit' => 'box', 'quantity' => 12, 'reserve' => 20, 'value' => 850, 'status' => 'active', 'location' => 'Health Station Cabinet', 'expiry' => null],
            ['key' => '005', 'name' => 'Reusable Raincoat', 'category' => 'clothing', 'subcategory' => 'jackets', 'unit' => 'piece', 'quantity' => 80, 'reserve' => 30, 'value' => 250, 'status' => 'active', 'location' => 'Relief Room', 'expiry' => null],
            ['key' => '006', 'name' => 'LED Emergency Lamp', 'category' => 'electronics', 'subcategory' => null, 'unit' => 'piece', 'quantity' => 0, 'reserve' => 10, 'value' => 650, 'status' => 'active', 'location' => 'Equipment Cabinet', 'expiry' => null],
            ['key' => '007', 'name' => 'Bottled Water 1.5L', 'category' => 'water_beverages', 'subcategory' => null, 'unit' => 'bottle', 'quantity' => 96, 'reserve' => 60, 'value' => 35, 'status' => 'active', 'location' => 'Main Storage C', 'expiry' => now()->addMonths(8)->toDateString()],
            ['key' => '008', 'name' => 'Blanket', 'category' => 'bedding_linens', 'subcategory' => null, 'unit' => 'piece', 'quantity' => 25, 'reserve' => 10, 'value' => 500, 'status' => 'active', 'location' => 'Relief Room', 'expiry' => null],
            ['key' => '009', 'name' => 'Used Folding Chair', 'category' => 'furniture', 'subcategory' => null, 'unit' => 'piece', 'quantity' => 18, 'reserve' => 5, 'value' => 400, 'status' => 'inactive', 'location' => 'Old Barangay Hall', 'expiry' => null],
            ['key' => '010', 'name' => 'Damaged Tarpaulin', 'category' => 'shelter_materials', 'subcategory' => null, 'unit' => 'piece', 'quantity' => 4, 'reserve' => 2, 'value' => 300, 'status' => 'archived', 'location' => 'Quarantine Storage', 'expiry' => null],
        ];

        foreach ($definitions as $definition) {
            $category = InventoryCategory::query()->where('key', $definition['category'])->first();
            $subcategory = $definition['subcategory'] === null || $category === null
                ? null
                : InventorySubcategory::query()->where('inventory_category_id', $category->id)->where('key', $definition['subcategory'])->first();
            $batch = 'SAMPLE-INV-'.$definition['key'];

            $item = InventoryItem::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'batch_number' => $batch],
                [
                    'workspace_id' => $workspaceId,
                    'name' => $definition['name'],
                    'category' => $definition['category'],
                    'inventory_subcategory_id' => $subcategory?->id,
                    'unit' => $definition['unit'],
                    'quantity' => $definition['quantity'],
                    'current_quantity' => $definition['quantity'],
                    'minimum_reserve_quantity' => $definition['reserve'],
                    'estimated_unit_value' => $definition['value'],
                    'expires_at' => $definition['expiry'],
                    'batch_number' => $batch,
                    'storage_location' => $definition['location'],
                    'description' => 'Sample inventory item for barangay relief operations.',
                    'notes' => 'Sample record; quantities are for demonstration only.',
                    'received_at' => now()->subDays(20),
                    'last_audited_at' => now()->subDays(2),
                    'last_movement_at' => now()->subDays(3),
                    'status' => $definition['status'],
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ],
            );

            InventoryStockMovement::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'reference_number' => 'SAMPLE-MOVE-'.$definition['key']],
                [
                    'workspace_id' => $workspaceId,
                    'inventory_item_id' => $item->id,
                    'created_by' => $adminId,
                    'movement_type' => 'stock_in',
                    'quantity' => $definition['quantity'],
                    'quantity_before' => 0,
                    'quantity_after' => $definition['quantity'],
                    'reason' => 'new_donation',
                    'reference_number' => 'SAMPLE-MOVE-'.$definition['key'],
                    'remarks' => 'Initial sample stock receipt.',
                ],
            );
        }
    }

    private function seedBankLedger(int $workspaceId, int $adminId, array $contributions, array $disbursements): void
    {
        $entries = [
            ['key' => '001', 'description' => 'Bank transfer - Maria Santos relief fund', 'amount' => 12000, 'type' => 'deposit', 'method' => 'bank_transfer', 'status' => 'reconciled', 'contribution' => '002'],
            ['key' => '002', 'description' => 'GCash transfer - Ana Reyes relief fund', 'amount' => 7500, 'type' => 'deposit', 'method' => 'gcash', 'status' => 'reconciled', 'contribution' => '003'],
            ['key' => '003', 'description' => 'Corporate bank transfer - Liza Navarro', 'amount' => 18500, 'type' => 'deposit', 'method' => 'bank_transfer', 'status' => 'reconciled', 'contribution' => '005'],
            ['key' => '004', 'description' => 'Maya transfer - Carmen Villanueva relief fund', 'amount' => 9800, 'type' => 'deposit', 'method' => 'maya', 'status' => 'reconciled', 'contribution' => '009'],
            ['key' => '005', 'description' => 'Community bank withdrawal - livelihood kits', 'amount' => 18000, 'type' => 'withdrawal', 'method' => 'bank_transfer', 'status' => 'reconciled', 'disbursement' => '001'],
            ['key' => '006', 'description' => 'Community bank withdrawal - senior citizen food packs', 'amount' => 9500, 'type' => 'withdrawal', 'method' => 'bank_transfer', 'status' => 'reconciled', 'disbursement' => '002'],
            ['key' => '007', 'description' => 'Community bank withdrawal - rain repair materials', 'amount' => 12400, 'type' => 'withdrawal', 'method' => 'bank_transfer', 'status' => 'reconciled', 'disbursement' => '003'],
            ['key' => '008', 'description' => 'Community bank withdrawal - health station medicines', 'amount' => 7200, 'type' => 'withdrawal', 'method' => 'bank_transfer', 'status' => 'reconciled', 'disbursement' => '004'],
            ['key' => '009', 'description' => 'Unmatched cash deposit - barangay fundraising booth', 'amount' => 4200, 'type' => 'deposit', 'method' => 'cash_deposit', 'status' => 'unmatched'],
            ['key' => '010', 'description' => 'Unmatched ATM withdrawal - emergency petty cash', 'amount' => 2500, 'type' => 'withdrawal', 'method' => 'atm', 'status' => 'unreconciled'],
        ];

        foreach ($entries as $entry) {
            $contribution = isset($entry['contribution']) ? $contributions[$entry['contribution']] : null;
            $ledger = BankLedgerEntry::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'statement_id' => 'SAMPLE-BANK-'.$entry['key']],
                [
                    'workspace_id' => $workspaceId,
                    'statement_id' => 'SAMPLE-BANK-'.$entry['key'],
                    'duplicate_fingerprint' => hash('sha256', 'SAMPLE-BANK-'.$entry['key']),
                    'description' => $entry['description'],
                    'reference_number' => 'SAMPLE-BANK-REF-'.$entry['key'],
                    'amount' => $entry['amount'],
                    'transaction_date' => now()->subDays(8 - (int) $entry['key'])->toDateString(),
                    'transaction_type' => $entry['type'],
                    'payment_method' => $entry['method'],
                    'status' => $entry['status'],
                    'matched_contribution_id' => $contribution?->id,
                    'matched_by' => $contribution !== null ? $adminId : null,
                    'matched_at' => $contribution !== null ? now()->subDays(2) : null,
                    'reconciled_by' => $entry['status'] === 'reconciled' ? $adminId : null,
                    'reconciled_at' => $entry['status'] === 'reconciled' ? now()->subDay() : null,
                    'notes' => $entry['status'] === 'reconciled' ? 'Matched sample transaction.' : 'Sample transaction awaiting review.',
                ],
            );

            if (isset($entry['disbursement'])) {
                BankWithdrawalDisbursementMatch::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'bank_ledger_entry_id' => $ledger->id, 'cash_disbursement_id' => $disbursements[$entry['disbursement']]->id],
                    ['workspace_id' => $workspaceId],
                );
            }
        }
    }

    private function seedReconciliationPeriods(int $workspaceId, int $adminId): void
    {
        $periods = [
            ['key' => 'approved', 'start' => now()->startOfMonth()->toDateString(), 'end' => now()->subDays(5)->toDateString(), 'opening' => 250000, 'cash_in' => 52000, 'cash_out' => 49600, 'status' => 'approved_locked'],
            ['key' => 'draft', 'start' => now()->subDays(4)->toDateString(), 'end' => now()->toDateString(), 'opening' => 248400, 'cash_in' => 0, 'cash_out' => 0, 'status' => 'draft'],
        ];

        foreach ($periods as $period) {
            $closing = $period['opening'] + $period['cash_in'] - $period['cash_out'];
            BankReconciliation::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'notes' => 'SAMPLE-RECON-'.$period['key']],
                [
                    'workspace_id' => $workspaceId,
                    'date' => $period['end'],
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'opening_balance' => $period['opening'],
                    'bank_ending_balance' => $closing,
                    'cash_in' => $period['cash_in'],
                    'cash_out' => $period['cash_out'],
                    'calculated_bank_cash_in' => $period['cash_in'],
                    'calculated_bank_cash_out' => $period['cash_out'],
                    'financial_ledger_cash_in' => $period['cash_in'],
                    'financial_ledger_cash_out' => $period['cash_out'],
                    'closing_balance' => $closing,
                    'financial_ledger_ending_balance' => $closing,
                    'reconciliation_difference' => 0,
                    'accounting_adjustment' => 0,
                    'adjusted_bank_balance' => $closing,
                    'unresolved_discrepancy_amount' => 0,
                    'status' => $period['status'],
                    'prepared_by' => $adminId,
                    'submitted_at' => $period['status'] === 'approved_locked' ? now()->subDays(3) : null,
                    'approved_by' => $period['status'] === 'approved_locked' ? $adminId : null,
                    'approved_at' => $period['status'] === 'approved_locked' ? now()->subDays(2) : null,
                    'calculated_at' => now()->subDay(),
                ],
            );
        }
    }
}
