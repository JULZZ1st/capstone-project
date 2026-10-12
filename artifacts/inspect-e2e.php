<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=barangay_donation_e2e_20261012', 'root', '@19#gina$06*');
foreach ($pdo->query('SELECT statement_id,transaction_type,amount,status FROM bank_ledger_entries') as $row) echo json_encode($row), PHP_EOL;
foreach ($pdo->query('SELECT id,status,period_start,period_end,opening_balance,bank_ending_balance,calculated_bank_cash_in,calculated_bank_cash_out,reconciliation_difference FROM bank_reconciliations') as $row) echo json_encode($row), PHP_EOL;
