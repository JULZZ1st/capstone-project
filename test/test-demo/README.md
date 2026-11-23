# Paid Fund Request reconciliation demo

- Database: `barangay_donation_system`
- Workspace: `Legacy Workspace` (#1)
- Staff requester/payment recorder: `staff@barangay.test`
- Admin approver: `admin@barangay.test`
- Workbook: `landbank-paid-fund-requests-demo.xlsx`

| Expected | Date | Reference | Amount | Direction | Description |
|---|---|---|---:|---|---|
| Fund Request match | 2026-11-05 | BR-DEMO-PAY-202611-001 | PHP 6,500.00 | withdrawal | FUND REQUEST PAYMENT - Demo Relief Goods Supplier |
| Fund Request match | 2026-11-12 | BR-DEMO-PAY-202611-002 | PHP 9,800.00 | withdrawal | FUND REQUEST PAYMENT - Demo Community Health Supplier |
| Fund Request match | 2026-11-19 | BR-DEMO-PAY-202611-003 | PHP 12,750.00 | withdrawal | FUND REQUEST PAYMENT - Demo Emergency Materials Supplier |
| Discrepancy | 2026-11-10 | BR-DEMO-UNMATCHED-FEE-001 | PHP 125.00 | withdrawal | UNMATCHED DEMO - LANDBANK SERVICE CHARGE |
| Discrepancy | 2026-11-24 | BR-DEMO-UNMATCHED-OUT-001 | PHP 3,333.33 | withdrawal | UNMATCHED DEMO - UNIDENTIFIED OUTGOING TRANSFER |
