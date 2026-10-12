<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_report_generator(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_financial_ledgers_report(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);
        $this->cashContribution(['amount' => 10000, 'payment_method' => 'gcash', 'received_at' => '2026-06-10', 'donor_name' => 'Enrique Razon']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('Report Generator');
        $response->assertSee('Financial Report');
        $response->assertSee('15,000');
        $response->assertSee('10,000');
        $response->assertSee('25,000');
        $response->assertSee('Dr. Manuel Co');
        $response->assertSee('Enrique Razon');
        $response->assertSee('Reconciled');
        $response->assertSee('Pending Ledger Clear');
    }

    public function test_month_filter_scopes_the_report(): void
    {
        $this->cashContribution(['amount' => 8000, 'payment_method' => 'gcash', 'received_at' => '2026-05-20', 'donor_name' => 'May Donor']);
        $this->cashContribution(['amount' => 12000, 'payment_method' => 'gcash', 'received_at' => '2026-06-20', 'donor_name' => 'June Donor']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('June Donor');
        $response->assertDontSee('May Donor');
    }

    public function test_wire_source_breakdown_only_lists_channels_with_activity(): void
    {
        $this->cashContribution(['amount' => 55000, 'payment_method' => 'gcash', 'received_at' => '2026-06-05']);
        $this->cashContribution(['amount' => 115000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-06']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('GCash');
        $response->assertSee('Bank Transfer');
        $response->assertDontSee('Check');
    }

    public function test_inventory_report_shows_real_inventory_metrics(): void
    {
        $item = InventoryItem::query()->create([
            'name' => 'Relief Rice', 'category' => 'food', 'unit' => 'sack',
            'quantity' => 25, 'current_quantity' => 25, 'minimum_reserve_quantity' => 10,
            'status' => 'active', 'received_at' => '2026-06-03',
        ]);
        InventoryStockMovement::query()->create([
            'inventory_item_id' => $item->id, 'movement_type' => 'stock_in', 'quantity' => 25,
            'quantity_before' => 0, 'quantity_after' => 25, 'reason' => 'Donation received',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'inventory', 'year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('Inventory Report');
        $response->assertSee('In-kind Received');
        $response->assertSee('Stock-in');
        $response->assertSee('Stock-out');
        $response->assertSee('Current Stock');
        $response->assertSee('Low Items');
        $response->assertSee('Critical Items');
        $response->assertSee('Expired Items');
        $response->assertSee('Relief Rice');
    }

    public function test_in_kind_audits_tab_lists_monthly_stock_movements(): void
    {
        $user = User::factory()->create();
        $item = $this->inventoryItem();
        $juneMovement = InventoryStockMovement::query()->create([
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'movement_type' => 'stock_out',
            'quantity' => 3,
            'quantity_before' => 10,
            'quantity_after' => 7,
            'reason' => 'distribution',
            'reference_number' => 'DIST-2026-06-01',
            'remarks' => 'Family relief distribution',
        ]);
        DB::table('inventory_stock_movements')->where('id', $juneMovement->id)->update(['created_at' => '2026-06-12 10:30:00']);

        $julyMovement = InventoryStockMovement::query()->create([
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'movement_type' => 'stock_in',
            'quantity' => 5,
            'quantity_before' => 7,
            'quantity_after' => 12,
            'reason' => 'new_donation',
        ]);
        DB::table('inventory_stock_movements')->where('id', $julyMovement->id)->update(['created_at' => '2026-07-02 10:30:00']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'in-kind-audits', 'year' => 2026, 'month' => 6]));

        $response->assertOk();
        $response->assertSee('In-Kind Audits');
        $response->assertSee('In-Kind Stock Activity');
        $response->assertSee('Hygiene Kits');
        $response->assertSee('Family relief distribution');
        $response->assertSee('DIST-2026-06-01');
        $response->assertSee('Stock Out');
        $response->assertDontSee('new_donation');
        $response->assertSee('/reports/in-kind-audits/csv?year=2026&amp;month=6', false);
        $response->assertSee('/reports/in-kind-audits/pdf?year=2026&amp;month=6', false);
    }

    public function test_authorized_user_can_download_in_kind_audit_csv_and_pdf(): void
    {
        $item = $this->inventoryItem();
        $movement = InventoryStockMovement::query()->create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 25,
            'quantity_before' => 0,
            'quantity_after' => 25,
            'reason' => 'new_donation',
        ]);
        DB::table('inventory_stock_movements')->where('id', $movement->id)->update(['created_at' => '2026-06-12 10:30:00']);

        $user = User::factory()->create();
        $csvResponse = $this->actingAs($user)
            ->get(route('reports.in-kind-audits.csv', ['year' => 2026, 'month' => 6]));

        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('Hygiene Kits', $csvResponse->streamedContent());

        $pdfResponse = $this->actingAs($user)
            ->get(route('reports.in-kind-audits.pdf', ['year' => 2026, 'month' => 6]));

        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
    }

    public function test_guest_cannot_download_in_kind_audit_exports(): void
    {
        $this->get(route('reports.in-kind-audits.csv'))->assertRedirect(route('login'));
        $this->get(route('reports.in-kind-audits.pdf'))->assertRedirect(route('login'));
    }

    public function test_philanthropy_roster_tab_renders_the_donor_giving_summary(): void
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => 'Philanthropy Donor',
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'philanthropy@example.com',
            'status' => config('donors.defaults.status'),
        ]);
        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 5000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.random_int(100000, 999999),
            'received_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'philanthropy-roster', 'year' => now()->year, 'month' => now()->month]));

        $response->assertOk();
        $response->assertSee('Philanthropy Roster');
        $response->assertSee('Donor Giving Summary');
        $response->assertSee('Philanthropy Donor');
        $response->assertSee('5,000.00');
    }

    public function test_philanthropy_roster_tab_shows_empty_state_with_no_giving(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'philanthropy-roster', 'year' => now()->year, 'month' => now()->month]));

        $response->assertOk();
        $response->assertSee('No donor giving activity found for this period.');
    }

    public function test_audit_report_shows_user_action_module_and_date_range(): void
    {
        $actor = User::factory()->create(['name' => 'Audit User']);
        SecurityActivityEvent::query()->create([
            'actor_id' => $actor->id, 'action' => 'inventory_stock_in',
            'summary' => 'Recorded relief stock', 'occurred_at' => '2026-06-15 10:30:00',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'audit', 'from' => '2026-06-01', 'to' => '2026-06-30']));

        $response->assertOk();
        $response->assertSee('Audit Trail Report');
        $response->assertSee('User');
        $response->assertSee('Action');
        $response->assertSee('Module');
        $response->assertSee('Date / Time');
        $response->assertSee('Audit User');
        $response->assertSee('Inventory');
    }

    public function test_authorized_user_can_download_financial_ledgers_csv(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.financial.csv', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Dr. Manuel Co', $content);
        $this->assertStringContainsString('15000.00', $content);
        $this->assertStringContainsString('Reconciled', $content);
    }

    public function test_guest_cannot_download_financial_ledgers_csv(): void
    {
        $this->get(route('reports.financial.csv'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_download_financial_ledgers_pdf(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.financial.pdf', ['year' => 2026, 'month' => 6]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guest_cannot_download_financial_ledgers_pdf(): void
    {
        $this->get(route('reports.financial.pdf'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_download_named_financial_ledger_exports(): void
    {
        $contribution = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($contribution);
        $user = User::factory()->create();

        $csv = $this->actingAs($user)
            ->get(route('reports.financial-ledgers.csv', ['year' => 2026, 'month' => 6]));
        $csv->assertOk();
        $this->assertStringContainsString('Dr. Manuel Co', $csv->streamedContent());
        $this->assertStringContainsString('15000.00', $csv->streamedContent());

        $pdf = $this->actingAs($user)
            ->get(route('reports.financial-ledgers.pdf', ['year' => 2026, 'month' => 6]));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_authorized_user_can_download_philanthropy_roster_pdf(): void
    {
        $this->cashContribution(['amount' => 5000, 'received_at' => now(), 'donor_name' => 'Philanthropy PDF Donor']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.philanthropy-roster.pdf', ['year' => now()->year, 'month' => now()->month]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_authorized_user_can_download_inventory_and_audit_exports(): void
    {
        $item = InventoryItem::query()->create([
            'name' => 'Export Rice', 'category' => 'food', 'unit' => 'sack',
            'quantity' => 3, 'current_quantity' => 3, 'minimum_reserve_quantity' => 1,
            'status' => 'active', 'received_at' => '2026-06-03',
        ]);
        InventoryStockMovement::query()->create([
            'inventory_item_id' => $item->id, 'movement_type' => 'stock_in', 'quantity' => 3,
            'quantity_before' => 0, 'quantity_after' => 3, 'reason' => 'Donation received',
        ]);
        $user = $this->actingAs(User::factory()->create());

        $csv = $user->get(route('reports.inventory.csv', ['year' => 2026, 'month' => 6]));
        $csv->assertOk();
        $this->assertStringContainsString('Export Rice', $csv->streamedContent());

        $pdf = $user->get(route('reports.inventory.pdf', ['year' => 2026, 'month' => 6]));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $audit = $user->get(route('reports.audit.csv', ['from' => '2026-01-01', 'to' => '2026-12-31']));
        $audit->assertOk();
        $this->assertStringContainsString('User,Action,Module,Date/Time,Details', $audit->streamedContent());
    }

    public function test_guests_cannot_download_inventory_or_audit_exports(): void
    {
        $this->get(route('reports.inventory.pdf'))->assertRedirect(route('login'));
        $this->get(route('reports.audit.pdf'))->assertRedirect(route('login'));
    }

    private function inventoryItem(): InventoryItem
    {
        return InventoryItem::query()->create([
            'name' => 'Hygiene Kits',
            'category' => 'food',
            'unit' => 'pack',
            'quantity' => 20,
            'current_quantity' => 20,
            'minimum_reserve_quantity' => 5,
            'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
            'received_at' => now(),
        ]);
    }

    private function cashContribution(array $overrides = []): Contribution
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => $overrides['donor_name'] ?? 'Test Donor '.random_int(1, 100000),
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 100000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 1000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.random_int(100000, 999999),
            'received_at' => now(),
        ], collect($overrides)->except('donor_name')->all()));
    }

    private function reconcile(Contribution $contribution): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create([
            'statement_id' => 'bt_'.random_int(1000, 9999),
            'description' => 'Bank credit',
            'reference_number' => $contribution->reference_number,
            'amount' => $contribution->amount,
            'transaction_date' => $contribution->received_at->toDateString(),
            'payment_method' => $contribution->payment_method,
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_at' => now(),
        ]);
    }
}
