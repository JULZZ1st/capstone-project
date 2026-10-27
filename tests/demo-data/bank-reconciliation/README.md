# Outgoing fund-request fixture

This fixture creates ten workspace-scoped `CashDisbursement` records:

- four approved requests;
- three pending requests; and
- three rejected requests.

The approved amounts are taken from the selected workspace's existing unreconciled LANDBANK withdrawal rows. The seeder does not create bank rows, change withdrawal statuses, create matches, or overwrite an existing request with the same fixture reference.

## Safe execution

The seeder requires an explicit existing Admin or Staff account. It resolves that account's single active workspace membership and refuses to continue if the workspace or maker-checker accounts are ambiguous.

Read-only preflight (Windows CMD):

```cmd
php artisan tinker --execute="dump(config('database.default')); dump(config('database.connections.'.config('database.default').'.database'));"
```

Before seeding, create and verify a backup. Confirm that `mysqldump` points to the intended MySQL installation and that the database name is correct:

```cmd
where mysqldump
if not exist backups mkdir backups
mysqldump --protocol=tcp -h 127.0.0.1 -P 3306 -u root --single-transaction --routines --triggers barangay_donation_system > backups\barangay_donation_system_pre_fixture.sql
certutil -hashfile backups\barangay_donation_system_pre_fixture.sql SHA256
```

After the database, workspace, and target account have been approved, run each command from the application root:

```cmd
set "BANK_RECONCILIATION_FIXTURE_USER_EMAIL=gina.gamboa.24@gmail.com"
php artisan db:seed --class=Database\Seeders\BankReconciliationOutgoingFixtureSeeder --no-interaction
set "BANK_RECONCILIATION_FIXTURE_USER_EMAIL="
```

The example account is the verified Staff account `gina.gamboa.24@gmail.com` in the current local `legacy` workspace. Replace it only with an explicitly approved active Admin or Staff account. The command intentionally has no `--env=demo` flag: it targets the already verified Laravel connection shown by the read-only preflight command.

For isolated validation, use the same seeder with the isolated environment selected before running the command and an isolated account email instead.

The seeder is idempotent. It uses the workspace-scoped `DEMO-FUND-REQUEST-001` through `DEMO-FUND-REQUEST-010` references, preserves existing rows, and only needs unreconciled withdrawal amounts for missing approved requests. It never creates bank transactions, matches withdrawals, changes statuses, or overwrites existing records.
