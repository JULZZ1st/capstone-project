<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Donor;
use App\Models\User;
use App\Services\Reports\DonorGivingSummaryReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorGivingSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_philanthropy_roster(): void
    {
        $this->get(route('reports.index', ['tab' => 'philanthropy-roster']))
            ->assertRedirect(route('login'));
    }

    public function test_it_totals_cash_and_in_kind_giving_per_donor(): void
    {
        $donor = $this->donor('Alice Santos');

        $cash = $this->cashContribution($donor, ['amount' => 100, 'received_at' => '2026-06-05']);
        $inKind = $this->inKindContribution($donor, ['received_at' => '2026-06-10']);
        $inKind->items()->create([
            'name' => 'Canned goods',
            'category' => 'food',
            'quantity' => 10,
            'unit' => 'box',
            'condition' => 'new',
            'estimated_value' => 50,
        ]);

        $report = app(DonorGivingSummaryReportBuilder::class)->build(['year' => 2026, 'month' => 6]);
        $row = $report['rows']->firstWhere('donor_id', $donor->id);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['total_donation_count']);
        $this->assertSame(100.0, $row['total_cash_donations']);
        $this->assertSame(50.0, $row['total_in_kind_donations']);
        $this->assertSame(150.0, $row['combined_donation_value']);
    }

    public function test_it_excludes_cancelled_and_voided_contributions(): void
    {
        $donor = $this->donor('Bob Reyes');

        $this->cashContribution($donor, ['amount' => 100, 'received_at' => '2026-06-05']);
        $this->cashContribution($donor, ['amount' => 999, 'status' => 'cancelled', 'received_at' => '2026-06-06']);
        $this->cashContribution($donor, ['amount' => 999, 'voided_at' => now(), 'received_at' => '2026-06-07']);

        $report = app(DonorGivingSummaryReportBuilder::class)->build(['year' => 2026, 'month' => 6]);
        $row = $report['rows']->firstWhere('donor_id', $donor->id);

        $this->assertSame(1, $row['total_donation_count']);
        $this->assertSame(100.0, $row['total_cash_donations']);
    }

    public function test_donors_with_no_giving_in_the_period_are_excluded(): void
    {
        $donor = $this->donor('No Giving Donor');

        $report = app(DonorGivingSummaryReportBuilder::class)->build(['year' => 2026, 'month' => 6]);

        $this->assertNull($report['rows']->firstWhere('donor_id', $donor->id));
    }

    public function test_sorting_options(): void
    {
        $high = $this->donor('High Giver');
        $low = $this->donor('Low Giver');

        $this->cashContribution($high, ['amount' => 1000, 'received_at' => '2026-06-05']);
        $this->cashContribution($low, ['amount' => 10, 'received_at' => '2026-06-06']);

        $highest = app(DonorGivingSummaryReportBuilder::class)->build(['year' => 2026, 'month' => 6, 'sort' => 'highest']);
        $this->assertSame('High Giver', $highest['rows']->first()['donor_name']);

        $lowest = app(DonorGivingSummaryReportBuilder::class)->build(['year' => 2026, 'month' => 6, 'sort' => 'lowest']);
        $this->assertSame('Low Giver', $lowest['rows']->first()['donor_name']);
    }

    public function test_search_filters_by_donor_name(): void
    {
        $match = $this->donor('Carmela Dela Cruz');
        $other = $this->donor('Someone Else');

        $this->cashContribution($match, ['amount' => 100, 'received_at' => '2026-06-05']);
        $this->cashContribution($other, ['amount' => 100, 'received_at' => '2026-06-05']);

        $report = app(DonorGivingSummaryReportBuilder::class)->build(['year' => 2026, 'month' => 6, 'search' => 'Carmela']);

        $this->assertCount(1, $report['rows']);
        $this->assertSame('Carmela Dela Cruz', $report['rows']->first()['donor_name']);
    }

    public function test_authorized_user_can_view_philanthropy_roster_via_http(): void
    {
        $donor = $this->donor('HTTP Donor');
        $this->cashContribution($donor, ['amount' => 250, 'received_at' => now()]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.index', ['tab' => 'philanthropy-roster', 'year' => now()->year, 'month' => now()->month]));

        $response->assertOk();
        $response->assertSee('HTTP Donor');
        $response->assertSee('250.00');
    }

    private function donor(string $name): Donor
    {
        return Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => $name,
            'address' => 'Brgy. San Jose',
            'contact_number' => '09'.random_int(100000000, 999999999),
            'email' => Str::slug($name).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);
    }

    private function cashContribution(Donor $donor, array $overrides = []): Contribution
    {
        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 1000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.random_int(100000, 999999),
            'received_at' => now(),
        ], $overrides));
    }

    private function inKindContribution(Donor $donor, array $overrides = []): Contribution
    {
        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'in_kind',
            'status' => 'received',
            'amount' => null,
            'received_at' => now(),
        ], $overrides));
    }
}
