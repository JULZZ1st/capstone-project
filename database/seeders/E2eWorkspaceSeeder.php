<?php

namespace Database\Seeders;

use App\Models\BankLedgerEntry;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'name');

        foreach ([
            ['a', 'E2E Workspace A'],
            ['b', 'E2E Workspace B'],
        ] as [$suffix, $name]) {
            $workspace = Workspace::query()->updateOrCreate(
                ['slug' => "e2e-workspace-{$suffix}"],
                ['name' => $name, 'status' => 'active'],
            );

            $users = [];
            foreach (['admin', 'staff'] as $role) {
                $email = "e2e.{$role}.{$suffix}@example.test";
                $user = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'role_id' => $roles[$role],
                        'first_name' => 'E2E',
                        'last_name' => ucfirst($role).strtoupper($suffix),
                        'name' => 'E2E '.ucfirst($role).strtoupper($suffix),
                        'password' => Hash::make('E2E!Pass12345'),
                        'must_change_password' => false,
                        'status' => 'active',
                    ],
                );
                $workspace->memberships()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['role_id' => $roles[$role], 'status' => 'active'],
                );
                $users[$role] = $user;
            }

            app(WorkspaceContext::class)->set($workspace);

            $category = InventoryCategory::query()->updateOrCreate(
                ['key' => "e2e-food-{$suffix}"],
                ['label' => "E2E Food {$suffix}", 'is_active' => true],
            );
            InventoryUnit::query()->updateOrCreate(
                ['key' => "e2e-box-{$suffix}"],
                ['label' => "E2E Box {$suffix}", 'is_active' => true],
            );

            $donor = Donor::query()->updateOrCreate(
                ['full_name' => "E2E Donor {$suffix}"],
                [
                    'donor_type' => 'individual',
                    'address' => "E2E Address {$suffix}",
                    'contact_number' => "0917000000".($suffix === 'a' ? '1' : '2'),
                    'email' => "e2e.donor.{$suffix}@example.test",
                    'status' => 'active',
                ],
            );
            $campaign = Campaign::query()->updateOrCreate(
                ['slug' => "e2e-campaign-{$suffix}"],
                ['name' => "E2E Campaign {$suffix}", 'status' => 'active'],
            );
            Contribution::query()->updateOrCreate(
                ['reference_number' => "E2E-REF-{$suffix}"],
                [
                    'donor_id' => $donor->id,
                    'campaign_id' => $campaign->id,
                    'created_by' => $users['staff']->id,
                    'type' => 'cash',
                    'status' => 'verified',
                    'amount' => $suffix === 'a' ? 111.00 : 222.00,
                    'payment_method' => 'cash',
                    'received_at' => now(),
                ],
            );
            InventoryItem::query()->updateOrCreate(
                ['name' => "E2E Inventory {$suffix}"],
                [
                    'category' => $category->key,
                    'unit' => "e2e-box-{$suffix}",
                    'quantity' => 5,
                    'current_quantity' => 5,
                    'minimum_reserve_quantity' => 1,
                    'estimated_unit_value' => 10,
                    'received_at' => now(),
                    'status' => 'received',
                    'created_by' => $users['staff']->id,
                ],
            );
            BankLedgerEntry::query()->updateOrCreate(
                ['statement_id' => "E2E-STATEMENT-{$suffix}"],
                [
                    'description' => "E2E Bank Entry {$suffix}",
                    'reference_number' => "E2E-BANK-{$suffix}",
                    'amount' => $suffix === 'a' ? 111.00 : 222.00,
                    'transaction_date' => now()->toDateString(),
                    'transaction_type' => 'deposit',
                    'payment_method' => 'bank_transfer',
                    'status' => 'unmatched',
                ],
            );
        }

        app(WorkspaceContext::class)->clear();
    }
}
