<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_bank_reconciliation_index(): void
    {
        $this->get(route('bank-reconciliation.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_the_workspace(): void
    {
        $entry = $this->ledgerEntry(['description' => 'BDO OVER THE COUNTER ICTSI FND']);
        $contribution = $this->electronicContribution(['reference_number' => $entry->reference_number]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Bank Reconciliation Workspace');
        $response->assertSee($entry->description);
        $response->assertSee($contribution->donor->full_name);
    }

    public function test_treasurer_can_import_csv_bank_transactions(): void
    {
        $csv = "statement_id,description,reference_number,amount,transaction_date,payment_method\n".
            "stmt-100,GCash incoming,GC-100,1250.50,2026-09-05,gcash\n";

        $response = $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ]);

        $response->assertRedirect(route('bank-reconciliation.index'));
        $this->assertDatabaseHas('bank_ledger_entries', [
            'statement_id' => 'stmt-100',
            'reference_number' => 'GC-100',
            'status' => 'unmatched',
        ]);
    }

    public function test_landbank_statement_previews_running_balance_and_confirms_only_after_review(): void
    {
        $csv = "Account Statement,,,,,,\n".
            "Beginning Balance,,,,,,1000.00\n".
            "Date,Description,REF,Check No.,Debit Amount,Credit Amount,Balance\n".
            "2026-09-05,Barangay donation,REF-001,,,500.00,1500.00\n".
            "2026-09-06,Relief supplies,,CHK-001,200.00,,1300.00\n".
            "Ending Balance,,,,,,1300.00\n";
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($treasurer)->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('landbank.csv', $csv),
        ])->assertRedirect(route('bank-reconciliation.index'))
            ->assertSessionHas('bank_statement_preview');

        $this->assertDatabaseCount('bank_ledger_entries', 0);
        $this->actingAs($treasurer)->get(route('bank-reconciliation.index'))
            ->assertOk()
            ->assertSee('LANDBANK statement preview')
            ->assertSee('withdrawal')
            ->assertSee('REF-001');

        $this->actingAs($treasurer)->post(route('bank-reconciliation.import.confirm'))
            ->assertRedirect(route('bank-reconciliation.index'));

        $this->assertDatabaseHas('bank_ledger_entries', [
            'reference_number' => 'REF-001', 'transaction_type' => 'deposit', 'amount' => 500,
        ]);
        $this->assertDatabaseHas('bank_ledger_entries', [
            'reference_number' => 'CHK-001', 'transaction_type' => 'withdrawal', 'amount' => 200,
        ]);
    }

    public function test_landbank_import_does_not_guess_ambiguous_direction(): void
    {
        $csv = "Beginning Balance,,,,,,1000.00\n".
            "Date,Description,REF,Check No.,Debit Amount,Credit Amount,Balance\n".
            "2026-09-05,Ambiguous row,REF-AMB,,100.00,50.00,1050.00\n".
            "Ending Balance,,,,,,1050.00\n";

        $treasurer = $this->userWithRole('treasurer');
        $this->actingAs($treasurer)->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('landbank-invalid.csv', $csv),
        ])->assertRedirect(route('bank-reconciliation.index'));

        $this->actingAs($treasurer)->get(route('bank-reconciliation.index'))
            ->assertSee('Both debit and credit are populated')
            ->assertDontSee('Confirm LANDBANK import');
        $this->assertDatabaseCount('bank_ledger_entries', 0);
    }

    public function test_treasurer_can_import_xlsx_rows_with_blank_cells(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bank-statement-');
        $zip = new ZipArchive();
        $this->assertNotFalse($path);
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>statement_id</t></si><si><t>description</t></si><si><t>reference_number</t></si><si><t>amount</t></si><si><t>transaction_date</t></si><si><t>stmt-xlsx-100</t></si><si><t>GCash deposit</t></si><si><t>2026-09-05</t></si></sst>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c></row><row r="2"><c r="A2" t="s"><v>5</v></c><c r="B2" t="s"><v>6</v></c><c r="D2"><v>1250.50</v></c><c r="E2" t="s"><v>7</v></c></row></sheetData></worksheet>
XML);
        $zip->close();
        $xlsx = file_get_contents($path);
        unlink($path);
        $this->assertNotFalse($xlsx);

        $response = $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.xlsx', $xlsx),
        ]);

        $response->assertRedirect(route('bank-reconciliation.index'));
        $this->assertDatabaseHas('bank_ledger_entries', [
            'statement_id' => 'stmt-xlsx-100',
            'description' => 'GCash deposit',
            'reference_number' => null,
            'amount' => 1250.50,
            'payment_method' => 'gcash',
        ]);
        $this->assertSame(
            '2026-09-05',
            BankLedgerEntry::query()->where('statement_id', 'stmt-xlsx-100')->firstOrFail()->transaction_date->toDateString(),
        );
    }

    public function test_duplicate_statement_ids_are_rejected_without_partial_import(): void
    {
        $this->ledgerEntry(['statement_id' => 'stmt-duplicate']);
        $csv = "statement_id,description,amount,transaction_date\n".
            "stmt-duplicate,Duplicate,100,2026-09-05\n".
            "stmt-new,New transaction,200,2026-09-05\n";

        $response = $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ]);

        $response->assertSessionHasErrors('statement');
        $this->assertDatabaseMissing('bank_ledger_entries', ['statement_id' => 'stmt-new']);
    }

    public function test_secretary_cannot_import_bank_transactions(): void
    {
        $response = $this->actingAs($this->userWithRole('secretary'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', "statement_id,description,amount,transaction_date\nstmt-1,Test,100,2026-09-05\n"),
        ]);

        $response->assertForbidden();
    }

    public function test_treasurer_can_manually_create_bank_withdrawal_for_review(): void
    {
        $treasurer = $this->userWithRole('treasurer');

        $response = $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.store'), [
            'transaction_date' => '2026-09-12',
            'reference_number' => 'WD-2026-001',
            'description' => 'ATM withdrawal for relief supplies',
            'amount' => 3500.75,
        ]);

        $response->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));
        $this->assertDatabaseHas('bank_ledger_entries', [
            'reference_number' => 'WD-2026-001',
            'description' => 'ATM withdrawal for relief supplies',
            'amount' => 3500.75,
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);
        $this->assertSame('2026-09-12', BankLedgerEntry::query()->firstOrFail()->transaction_date->toDateString());
    }

    public function test_admin_can_manually_create_bank_withdrawal_for_review(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('bank-reconciliation.withdrawals.store'), [
            'transaction_date' => '2026-09-12',
            'reference_number' => 'WD-ADMIN-001',
            'description' => 'Counter withdrawal',
            'amount' => 1500,
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $this->assertDatabaseHas('bank_ledger_entries', [
            'reference_number' => 'WD-ADMIN-001',
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);
    }

    public function test_imported_withdrawal_rows_appear_under_outgoing_review(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $csv = "statement_id,description,reference_number,amount,transaction_date,transaction_type\n".
            "stmt-wd-100,Bank withdrawal for supplies,WD-IMPORT-100,4200.00,2026-09-10,withdrawal\n";

        $this->actingAs($treasurer)->post(route('bank-reconciliation.import'), [
            'return_tab' => 'outgoing',
            'statement' => UploadedFile::fake()->createWithContent('withdrawals.csv', $csv),
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $this->assertDatabaseHas('bank_ledger_entries', [
            'statement_id' => 'stmt-wd-100',
            'reference_number' => 'WD-IMPORT-100',
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);

        $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.index', ['tab' => 'outgoing']))
            ->assertOk()
            ->assertSee('Bank Withdrawals to Review')
            ->assertSee('Bank withdrawal for supplies')
            ->assertSee('WD-IMPORT-100')
            ->assertSee('PHP 4,200.00')
            ->assertSee('withdrawal')
            ->assertSee('unreconciled');
    }

    public function test_duplicate_withdrawal_reference_date_amount_is_rejected(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $this->ledgerEntry([
            'statement_id' => 'withdrawal-existing',
            'reference_number' => 'WD-DUP-001',
            'amount' => 2000,
            'transaction_date' => '2026-09-12',
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.store'), [
            'transaction_date' => '2026-09-12',
            'reference_number' => 'WD-DUP-001',
            'description' => 'Duplicate withdrawal',
            'amount' => 2000,
        ])->assertSessionHasErrors('reference_number');

        $this->assertSame(1, BankLedgerEntry::query()->where('reference_number', 'WD-DUP-001')->count());
    }

    public function test_import_duplicate_withdrawal_reference_date_amount_is_rejected(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $this->ledgerEntry([
            'statement_id' => 'withdrawal-import-existing',
            'reference_number' => 'WD-IMPORT-DUP',
            'amount' => 3200,
            'transaction_date' => '2026-09-12',
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);
        $csv = "statement_id,description,reference_number,amount,transaction_date,transaction_type\n".
            "stmt-wd-new,Duplicate imported withdrawal,WD-IMPORT-DUP,3200.00,2026-09-12,withdrawal\n";

        $this->actingAs($treasurer)->post(route('bank-reconciliation.import'), [
            'return_tab' => 'outgoing',
            'statement' => UploadedFile::fake()->createWithContent('withdrawals.csv', $csv),
        ])->assertSessionHasErrors('statement');

        $this->assertDatabaseMissing('bank_ledger_entries', ['statement_id' => 'stmt-wd-new']);
    }

    public function test_secretary_cannot_create_or_import_withdrawals_server_side(): void
    {
        $secretary = $this->userWithRole('secretary');
        $csv = "statement_id,description,reference_number,amount,transaction_date,transaction_type\n".
            "stmt-secretary-wd,Secretary withdrawal,WD-SEC-001,100,2026-09-12,withdrawal\n";

        $this->actingAs($secretary)->post(route('bank-reconciliation.withdrawals.store'), [
            'transaction_date' => '2026-09-12',
            'reference_number' => 'WD-SEC-001',
            'description' => 'Secretary withdrawal',
            'amount' => 100,
        ])->assertForbidden();

        $this->actingAs($secretary)->post(route('bank-reconciliation.import'), [
            'return_tab' => 'outgoing',
            'statement' => UploadedFile::fake()->createWithContent('withdrawals.csv', $csv),
        ])->assertForbidden();

        $this->assertDatabaseMissing('bank_ledger_entries', ['reference_number' => 'WD-SEC-001']);
    }

    public function test_one_bank_withdrawal_can_match_multiple_approved_disbursements_and_show_ready_when_balanced(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry(['amount' => 5000, 'reference_number' => 'WD-MATCH-READY']);
        $first = $this->cashDisbursement(['amount' => 2000, 'purpose' => 'Approved medicine payout']);
        $second = $this->cashDisbursement(['amount' => 3000, 'purpose' => 'Approved food payout']);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $withdrawal), [
            'disbursement_ids' => [$first->id, $second->id],
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $this->assertDatabaseHas('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $first->id,
        ]);
        $this->assertDatabaseHas('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $second->id,
        ]);

        $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.index', ['tab' => 'outgoing']))
            ->assertOk()
            ->assertSee('Selected Disbursements Total')
            ->assertSee('PHP 5,000.00')
            ->assertSee('Difference')
            ->assertSee('PHP 0.00')
            ->assertSee('Ready to Reconcile')
            ->assertSee('Approved medicine payout')
            ->assertSee('Approved food payout');
    }

    public function test_non_zero_withdrawal_match_difference_shows_discrepancy(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry(['amount' => 6000, 'reference_number' => 'WD-MATCH-DIFF']);
        $disbursement = $this->cashDisbursement(['amount' => 2500, 'purpose' => 'Partial approved payout']);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $withdrawal), [
            'disbursement_ids' => [$disbursement->id],
            'discrepancy_reason' => 'Bank fee was deducted before the disbursement cleared.',
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $this->assertDatabaseHas('bank_ledger_entries', [
            'id' => $withdrawal->id,
            'discrepancy_amount' => 3500,
            'discrepancy_reason' => 'Bank fee was deducted before the disbursement cleared.',
            'status' => 'unreconciled',
        ]);

        $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.index', ['tab' => 'outgoing']))
            ->assertOk()
            ->assertSee('Bank Withdrawal Amount')
            ->assertSee('PHP 6,000.00')
            ->assertSee('Selected Disbursements Total')
            ->assertSee('PHP 2,500.00')
            ->assertSee('Difference')
            ->assertSee('PHP 3,500.00')
            ->assertSee('Discrepancy')
            ->assertSee('Bank fee was deducted before the disbursement cleared.');
    }

    public function test_balanced_withdrawal_can_be_finalized(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry(['amount' => 5000, 'reference_number' => 'WD-FINALIZE']);
        $first = $this->cashDisbursement(['amount' => 2000]);
        $second = $this->cashDisbursement(['amount' => 3000]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.reconcile', $withdrawal), [
            'disbursement_ids' => [$first->id, $second->id],
            'finalize' => 1,
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $this->assertDatabaseHas('bank_ledger_entries', [
            'id' => $withdrawal->id,
            'status' => 'reconciled',
            'reconciled_by' => $treasurer->id,
            'discrepancy_amount' => null,
            'discrepancy_reason' => null,
        ]);
        $this->assertNotNull(BankLedgerEntry::query()->findOrFail($withdrawal->id)->reconciled_at);
        $this->assertDatabaseHas('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $first->id,
        ]);
        $this->assertDatabaseHas('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $second->id,
        ]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $withdrawal), [
            'disbursement_ids' => [$first->id],
        ])->assertSessionHasErrors('withdrawal');
    }

    public function test_finalized_withdrawal_history_has_details_and_is_read_only(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry([
            'amount' => 5000,
            'reference_number' => 'WD-HISTORY-001',
            'transaction_date' => '2026-09-13',
        ]);
        $disbursement = $this->cashDisbursement([
            'amount' => 5000,
            'purpose' => 'Finalized relief payout',
            'recipient' => 'Barangay Resident',
        ]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.reconcile', $withdrawal), [
            'disbursement_ids' => [$disbursement->id],
            'finalize' => 1,
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $historyResponse = $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.index', ['tab' => 'outgoing']))
            ->assertOk();

        $historyResponse
            ->assertSee('Reconciliation History')
            ->assertSee('Date')
            ->assertSee('Bank Reference')
            ->assertSee('Type')
            ->assertSee('Bank Amount')
            ->assertSee('Matched Amount')
            ->assertSee('Difference')
            ->assertSee('Status')
            ->assertSee('Reconciled By')
            ->assertSee('2026-09-13')
            ->assertSee('WD-HISTORY-001')
            ->assertSee('withdrawal')
            ->assertSee('PHP 5,000.00')
            ->assertSee('PHP 0.00')
            ->assertSee('reconciled')
            ->assertSee($treasurer->name)
            ->assertSee('View Details');

        $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.withdrawals.show', $withdrawal))
            ->assertOk()
            ->assertSee('Withdrawal Reconciliation Details')
            ->assertSee('Finalized relief payout')
            ->assertSee('WD-HISTORY-001')
            ->assertSee('PHP 5,000.00')
            ->assertSee('Matched Disbursements')
            ->assertSee('Reconciled By')
            ->assertSee('Reconciled At')
            ->assertSee($treasurer->name)
            ->assertSee('reconciled')
            ->assertDontSee('Save Match Selection')
            ->assertDontSee('Confirm Reconciliation');
    }

    public function test_discrepant_withdrawal_cannot_be_finalized(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry(['amount' => 6000, 'reference_number' => 'WD-FINALIZE-DIFF']);
        $disbursement = $this->cashDisbursement(['amount' => 2500]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.reconcile', $withdrawal), [
            'disbursement_ids' => [$disbursement->id],
            'discrepancy_reason' => 'Pending liquidation.',
            'finalize' => 1,
        ])->assertSessionHasErrors('finalize');

        $this->assertDatabaseHas('bank_ledger_entries', [
            'id' => $withdrawal->id,
            'status' => 'unreconciled',
            'reconciled_by' => null,
            'reconciled_at' => null,
        ]);
        $this->assertDatabaseMissing('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $disbursement->id,
        ]);
    }

    public function test_discrepancy_requires_an_explanation(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry(['amount' => 6000, 'reference_number' => 'WD-MATCH-REASON']);
        $disbursement = $this->cashDisbursement(['amount' => 2500]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $withdrawal), [
            'disbursement_ids' => [$disbursement->id],
        ])->assertSessionHasErrors('discrepancy_reason');

        $this->assertDatabaseMissing('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $disbursement->id,
        ]);
        $this->assertDatabaseHas('bank_ledger_entries', [
            'id' => $withdrawal->id,
            'status' => 'unreconciled',
            'discrepancy_amount' => null,
            'discrepancy_reason' => null,
        ]);
    }

    public function test_only_approved_active_unmatched_disbursements_are_selectable_for_withdrawal_matching(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry(['reference_number' => 'WD-ELIGIBLE']);
        $otherWithdrawal = $this->withdrawalEntry(['reference_number' => 'WD-OTHER']);
        $eligible = $this->cashDisbursement(['purpose' => 'Eligible approved disbursement']);
        $pending = $this->cashDisbursement(['purpose' => 'Pending hidden disbursement', 'status' => 'pending', 'approved_by' => null, 'approved_at' => null]);
        $rejected = $this->cashDisbursement(['purpose' => 'Rejected hidden disbursement', 'status' => 'rejected', 'approved_by' => null, 'approved_at' => null]);
        $voided = $this->cashDisbursement(['purpose' => 'Voided hidden disbursement', 'voided_at' => now(), 'voided_by' => $treasurer->id, 'void_reason' => 'Cancelled']);
        $alreadyMatched = $this->cashDisbursement(['purpose' => 'Already matched hidden disbursement']);
        $otherWithdrawal->matchedDisbursements()->sync([$alreadyMatched->id]);

        $response = $this->actingAs($treasurer)->get(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $response->assertOk()
            ->assertSee('Eligible approved disbursement')
            ->assertDontSee('Pending hidden disbursement')
            ->assertDontSee('Rejected hidden disbursement')
            ->assertDontSee('Voided hidden disbursement');

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $withdrawal), [
            'disbursement_ids' => [$pending->id, $rejected->id, $voided->id, $alreadyMatched->id],
        ])->assertSessionHasErrors('disbursement_ids');

        $this->assertDatabaseMissing('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $pending->id,
        ]);
        $this->assertDatabaseMissing('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $eligible->id,
        ]);
    }

    public function test_same_disbursement_cannot_be_matched_to_more_than_one_withdrawal(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $firstWithdrawal = $this->withdrawalEntry(['reference_number' => 'WD-FIRST']);
        $secondWithdrawal = $this->withdrawalEntry(['reference_number' => 'WD-SECOND']);
        $disbursement = $this->cashDisbursement(['purpose' => 'Single-use approved payout']);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $firstWithdrawal), [
            'disbursement_ids' => [$disbursement->id],
        ])->assertRedirect(route('bank-reconciliation.index', ['tab' => 'outgoing']));

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $secondWithdrawal), [
            'disbursement_ids' => [$disbursement->id],
        ])->assertSessionHasErrors('disbursement_ids');

        $this->assertDatabaseHas('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $firstWithdrawal->id,
            'cash_disbursement_id' => $disbursement->id,
        ]);
        $this->assertDatabaseMissing('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $secondWithdrawal->id,
            'cash_disbursement_id' => $disbursement->id,
        ]);
    }

    public function test_already_reconciled_withdrawal_cannot_be_matched_again(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $withdrawal = $this->withdrawalEntry([
            'reference_number' => 'WD-RECONCILED',
            'status' => 'reconciled',
        ]);
        $disbursement = $this->cashDisbursement(['purpose' => 'Approved payout blocked by reconciled withdrawal']);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.withdrawals.matches.store', $withdrawal), [
            'disbursement_ids' => [$disbursement->id],
        ])->assertSessionHasErrors('withdrawal');

        $this->assertDatabaseMissing('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $disbursement->id,
        ]);
    }

    public function test_matching_reference_number_surfaces_as_a_suggestion(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'BDO-TRF-55419', 'amount' => 100000]);
        $contribution = $this->electronicContribution(['reference_number' => 'BDO-TRF-55419', 'amount' => 100000]);
        $this->electronicContribution(['reference_number' => 'UNRELATED-REF', 'amount' => 5000]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('1 suggestion found');
        $response->assertSee($contribution->donor->full_name);
        $response->assertSee($contribution->receipt_number);
    }

    public function test_unverified_electronic_contributions_are_not_match_candidates(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'UNVERIFIED-REF']);
        $contribution = $this->electronicContribution([
            'reference_number' => 'UNVERIFIED-REF',
            'status' => 'pending',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertDontSee($contribution->donor->full_name);
        $response->assertSee($entry->description);
    }

    public function test_treasurer_can_reconcile_a_matched_entry(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);
        $treasurer = $this->userWithRole('treasurer');

        $response = $this->actingAs($treasurer)->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $response->assertRedirect(route('bank-reconciliation.index'));
        $this->assertDatabaseHas('bank_ledger_entries', [
            'id' => $entry->id,
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_by' => $treasurer->id,
        ]);
    }

    public function test_reconciliation_is_recorded_in_the_security_audit_log(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-AUDIT', 'amount' => 2500]);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-AUDIT', 'amount' => 2500]);
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($treasurer)->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $this->assertDatabaseHas('security_activity_events', [
            'action' => 'bank_reconciliation_matched',
            'actor_id' => $treasurer->id,
        ]);
    }

    public function test_bank_statement_import_is_recorded_in_the_security_audit_log(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $csv = "statement_id,description,reference_number,amount,transaction_date,payment_method\n".
            "stmt-audit,GCash incoming,GC-AUDIT,750.00,2026-09-05,gcash\n";

        $this->actingAs($treasurer)->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ]);

        $this->assertDatabaseHas('security_activity_events', [
            'action' => 'bank_statement_imported',
            'actor_id' => $treasurer->id,
        ]);
    }

    public function test_reconciled_transactions_appear_in_history(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-HISTORY']);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-HISTORY']);
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($treasurer)->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.index'))
            ->assertOk()
            ->assertSee('Reconciliation History')
            ->assertSee($contribution->receipt_number);
    }

    public function test_reconciled_entry_no_longer_appears_in_the_workspace(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);

        $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Reconciliation History');
        $response->assertSee($entry->description);
        $response->assertSee($contribution->donor->full_name);
    }

    public function test_secretary_cannot_reconcile_an_entry(): void
    {
        $entry = $this->ledgerEntry();
        $contribution = $this->electronicContribution();

        $response = $this->actingAs($this->userWithRole('secretary'))->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('bank_ledger_entries', ['id' => $entry->id, 'status' => 'unmatched']);
    }

    private function ledgerEntry(array $overrides = []): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create(array_merge([
            'statement_id' => 'bt_'.random_int(1000, 9999),
            'description' => 'BDO OVER THE COUNTER ICTSI FND',
            'reference_number' => 'BDO-TRF-'.random_int(10000, 99999),
            'amount' => 1000,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'status' => 'unmatched',
        ], $overrides));
    }

    private function withdrawalEntry(array $overrides = []): BankLedgerEntry
    {
        return $this->ledgerEntry(array_merge([
            'statement_id' => 'wd_'.random_int(1000, 9999),
            'description' => 'Bank withdrawal',
            'reference_number' => 'WD-'.random_int(10000, 99999),
            'amount' => 1000,
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ], $overrides));
    }

    private function cashDisbursement(array $overrides = []): CashDisbursement
    {
        $requester = $this->userWithRole('treasurer');
        $approver = $this->userWithRole('admin');

        return CashDisbursement::query()->create(array_merge([
            'amount' => 1000,
            'purpose' => 'Approved cash assistance',
            'recipient' => 'Barangay Resident',
            'requested_date' => '2026-09-12',
            'requested_by' => $requester->id,
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ], $overrides));
    }

    private function electronicContribution(array $overrides = []): Contribution
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => 'Enrique Razon',
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 100000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 1000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BDO-TRF-'.random_int(10000, 99999),
            'received_at' => now(),
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
