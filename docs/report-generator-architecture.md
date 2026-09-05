Report Generator Architecture

Overview

The report generator follows a simple, testable architecture:

- Report Builders: Responsible for assembling datasets and summaries using optimized queries. Example: `FinancialLedgerReportBuilder`, `DonorGivingSummaryReportBuilder`.
- Exporters: Responsible for rendering and exporting builder output. Examples: `FinancialLedgerCsvExporter`, `FinancialLedgerPdfExporter`.
- Controllers: Accept HTTP requests, validate filters and sorting, authorize the user, and orchestrate builder + exporter or view rendering.
- Views: Blade templates for PDF rendering and HTML tables. Keep PDF views simple and dompdf-compatible (use inline CSS, avoid external assets).

Principles

- Single Responsibility: Builders only assemble data; exporters only format/export.
- Dependency Injection: Inject builders into exporters and controllers so components are easily testable and replaceable.
- Performance: Use eager loading and database aggregates (`withCount`, `withSum`, `withMin`, `withMax`) to avoid N+1 queries.
- Parity: CSV and PDF exporters use the exact same builder output to ensure identical row/summary data.

Typical flow

1. Controller validates request filters and checks authorization.
2. Controller calls a Builder with filters.
3. Builder returns `['rows'=>Collection, 'summary'=>array, 'filters'=>array]` or similar.
4. Controller passes data to an Exporter (CSV/PDF) or to a Blade view for on-screen rendering.

Testing

- Unit test builders for correct aggregates and filtering.
- Feature tests for endpoints: authorization, content type, empty states, and parity between exporters.
