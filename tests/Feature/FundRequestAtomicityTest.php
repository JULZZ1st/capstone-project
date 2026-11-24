<?php

namespace Tests\Feature;

use App\Models\CashDisbursement;
use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;

class FundRequestAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_certification_cannot_overwrite_an_existing_certification(): void
    {
        [$staff, $fund] = $this->fund();
        Gate::after(function (User $user, string $ability, $result) use ($staff, $fund): void {
            if ($ability === 'recordTreasurerCertification' && $result === true) {
                DB::table('cash_disbursements')->where('id', $fund->id)->update([
                    'treasurer_certified_by' => $staff->id, 'treasurer_certified_at' => now(),
                    'treasurer_certification_notes' => 'Existing certification',
                ]);
            }
        });

        $this->actingAs($staff)->post(route('disbursements.treasurer-certification.store', $fund), [
            'certified' => '1', 'notes' => 'Stale certification',
        ])->assertForbidden();

        $this->assertSame('Existing certification', $fund->fresh()->treasurer_certification_notes);
        $this->assertDatabaseMissing('security_activity_events', ['action' => 'disbursement_treasurer_certified']);
    }

    public function test_failed_certification_audit_rolls_back_the_certification(): void
    {
        [$staff, $fund] = $this->fund();
        $this->failAudit('disbursement_treasurer_certified');

        try {
            $this->withoutExceptionHandling()->actingAs($staff)->post(route('disbursements.treasurer-certification.store', $fund), ['certified' => '1']);
            $this->fail('Certification succeeded without an audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertNull($fund->fresh()->treasurer_certified_at);
    }

    public function test_failed_edit_audit_rolls_back_the_amount_and_certification(): void
    {
        [$staff, $fund] = $this->fund();
        $fund->update(['treasurer_certified_by' => $staff->id, 'treasurer_certified_at' => now()]);
        $this->failAudit('disbursement_updated');

        try {
            $this->withoutExceptionHandling()->actingAs($staff)->patch(route('disbursements.update', $fund), [
                'amount' => 2500, 'purpose' => 'Emergency supplies', 'recipient' => 'Community Pharmacy',
                'payee_address' => 'Poblacion', 'fund_source' => 'General Fund', 'requested_date' => '2026-11-01',
            ]);
            $this->fail('An edit succeeded without an audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertSame('1250.00', $fund->fresh()->amount);
        $this->assertNotNull($fund->fresh()->treasurer_certified_at);
    }

    public function test_cancelled_request_cannot_have_its_voucher_review_changed(): void
    {
        [$staff, $fund] = $this->fund();
        $fund->update(['status' => 'approved', 'voided_at' => now()]);
        $role = Role::firstOrCreate(['name' => 'admin'], config('auth_defaults.roles.admin'));
        $admin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->patch(route('disbursements.signed-voucher.request', $fund), [
            'reason' => 'Request a new signature',
        ])->assertForbidden();

        $this->assertNull($fund->fresh()->signed_voucher_request_reason);
    }

    private function fund(): array
    {
        $staff = User::factory()->create();

        return [$staff, CashDisbursement::create([
            'requested_by' => $staff->id, 'amount' => 1250, 'purpose' => 'Emergency supplies',
            'recipient' => 'Community Pharmacy', 'status' => 'pending',
        ])];
    }

    public function test_fund_requests_reject_fractional_cents_without_creating_a_record(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('disbursements.store'), [
            'amount' => '1250.001', 'purpose' => 'Emergency supplies', 'recipient' => 'Community Pharmacy',
            'payee_address' => 'Poblacion', 'fund_source' => 'General Fund', 'requested_date' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('cash_disbursements', 0);
    }

    public function test_payments_reject_fractional_cents_without_recording_a_payment(): void
    {
        [$staff, $fund] = $this->fund();
        $fund->update(['status' => 'approved']);

        $this->actingAs($staff)->postJson(route('disbursements.payment.store', $fund), [
            'amount' => '1250.001', 'payment_date' => '2026-11-12', 'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('cash_disbursement_payments', 0);
    }

    private function failAudit(string $action): void
    {
        SecurityActivityEvent::creating(function (SecurityActivityEvent $event) use ($action): void {
            if ($event->action === $action) {
                throw new RuntimeException('Audit unavailable.');
            }
        });
    }
}
