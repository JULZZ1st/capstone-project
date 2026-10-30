<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Services\BankReconciliation\ImportBankStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class BankStatementImportRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extra_csv_columns_return_validation_errors_without_importing_rows(): void
    {
        $this->import("statement_id,description,amount,transaction_date\nbad,Deposit,100,2026-09-05,extra\n")
            ->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('bank_ledger_entries', 0);
    }

    public function test_duplicate_csv_headers_are_rejected_instead_of_overwriting_data(): void
    {
        $this->import("statement_id,description,amount,transaction_date, AMOUNT \nbad,Deposit,100,2026-09-05,200\n")
            ->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('bank_ledger_entries', 0);
    }

    public function test_csv_utf8_bom_does_not_hide_the_statement_id_header(): void
    {
        $this->import("\xEF\xBB\xBFstatement_id,description,amount,transaction_date\nbom-1,Deposit,100,2026-09-05\n")
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('bank_ledger_entries', ['statement_id' => 'bom-1', 'amount' => 100]);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_rolls_back_the_entire_statement(string $amount): void
    {
        $csv = "statement_id,description,amount,transaction_date\nvalid,First deposit,100,2026-09-05\n".
            "invalid,Second deposit,{$amount},2026-09-06\n";
        $this->import($csv)->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('bank_ledger_entries', 0);
        $this->assertDatabaseMissing('security_activity_events', ['action' => 'bank_statement_imported']);
    }

    public static function invalidAmounts(): array
    {
        return ['zero' => ['0'], 'negative' => ['-10'], 'overflow' => ['10000000000']];
    }

    public function test_xlsx_inline_and_rich_text_cells_and_blank_rows_import_correctly(): void
    {
        $sheet = <<<'XML'
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
<row r="1"><c r="A1" t="inlineStr"><is><t>statement_id</t></is></c><c r="B1" t="inlineStr"><is><t>description</t></is></c><c r="C1" t="inlineStr"><is><t>amount</t></is></c><c r="D1" t="inlineStr"><is><t>transaction_date</t></is></c></row>
<row r="2"><c r="A2" t="inlineStr"><is><t>inline-1</t></is></c><c r="B2" t="inlineStr"><is><r><t>GCash </t></r><r><t>deposit</t></r></is></c><c r="C2"><v>1250.50</v></c><c r="D2" t="inlineStr"><is><t>2026-09-05</t></is></c></row>
<row r="3"><c r="A3" s="1"/></row><row r="4"/>
</sheetData></worksheet>
XML;
        $this->importXlsx(['xl/worksheets/sheet1.xml' => $sheet])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('bank_ledger_entries', [
            'statement_id' => 'inline-1', 'description' => 'GCash deposit', 'amount' => 1250.50,
        ]);
        $this->assertDatabaseCount('bank_ledger_entries', 1);
    }

    #[DataProvider('brokenWorksheets')]
    public function test_broken_xlsx_returns_a_validation_error(array $parts): void
    {
        $this->importXlsx($parts)->assertSessionHasErrors('statement');
        $this->assertDatabaseCount('bank_ledger_entries', 0);
    }

    public static function brokenWorksheets(): array
    {
        return [
            'missing worksheet' => [['other.xml' => '<other/>']],
            'malformed worksheet' => [['xl/worksheets/sheet1.xml' => '<worksheet><sheetData>']],
        ];
    }

    public function test_audit_failure_rolls_back_imported_transactions(): void
    {
        SecurityActivityEvent::creating(function (SecurityActivityEvent $event): void {
            if ($event->action === 'bank_statement_imported') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        $file = UploadedFile::fake()->createWithContent('statement.csv',
            "statement_id,description,amount,transaction_date\naudit-1,Deposit,100,2026-09-05\n");

        try {
            app(ImportBankStatement::class)->handle($file, User::factory()->create());
            $this->fail('The simulated audit failure should propagate.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit unavailable', $error->getMessage());
        }
        $this->assertDatabaseCount('bank_ledger_entries', 0);
    }

    private function importXlsx(array $parts): TestResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'bank-regression-');
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
            foreach ($parts as $name => $xml) {
                $zip->addFromString($name, $xml);
            }
            $zip->close();

            return $this->import(file_get_contents($path), 'xlsx');
        } finally {
            unlink($path);
        }
    }

    private function import(string $content, string $extension = 'csv'): TestResponse
    {
        $role = Role::query()->firstOrCreate(['name' => 'staff'], config('auth_defaults.roles.staff'));

        return $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->post(route('bank-reconciliation.import'), [
                'statement' => UploadedFile::fake()->createWithContent('statement.'.$extension, $content),
            ]);
    }
}
