PDF Export Setup (barryvdh/laravel-dompdf)

> Status: NOT implemented in this app yet. This file is reference material only —
> copied from Neziel's WIP for when PDF export is picked up. `barryvdh/laravel-dompdf`
> is not in `composer.json`, and no PDF exporter/route/view exists in `app/` or
> `routes/web.php`. See `docs/TEAM_TASK_ASSIGNMENT.md` (A12/S12 — outstanding).

1. Install the package

Recommended — global Composer installed:

```bash
cd /path/to/project
composer require barryvdh/laravel-dompdf
```

If you don't have `composer` globally, use the `composer.phar` approach.

2. (Optional) Publish configuration

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

This will create `config/dompdf.php` for fine-tuning paper size, DPI, and font directories.

3. Usage

- Use the facade in code:

```php
use Barryvdh\DomPDF\Facade\Pdf as Pdf;

$pdf = Pdf::loadView('reports.pdf.financial-ledger', $data)->setPaper('a4', 'landscape');
return response()->streamDownload(function() use ($pdf) {
    echo $pdf->output();
}, 'financial-ledger.pdf', ['Content-Type' => 'application/pdf']);
```

4. Fonts and UTF-8

- Dompdf requires fonts that support your character set. For UTF-8, use DejaVu fonts or configure your font directory in `config/dompdf.php`.

5. Common issues

- CSS support is limited. Use inline styles and avoid external JS/CSS assets.
- If the PDF is blank, enable `log_output_file` in `config/dompdf.php` and check Laravel logs.
- For large data, prefer streaming and paginate if necessary.

6. CI / Tests

- When running tests in CI, ensure `ext-gd` or `ext-mbstring` are available; dompdf may require these PHP extensions.
