<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body{font-family:DejaVu Sans, sans-serif;color:#111827;font-size:10px}
    .status{float:right;border:1px solid #f59e0b;color:#92400e;padding:3px 7px;font-weight:bold}
    .center{text-align:center}
    .right{text-align:right}
    .wrap{width:100%;margin:0 auto}
    table.form{width:100%;border-collapse:collapse;margin-bottom:4px}
    table.form td{border:1px solid #000;padding:3px 6px;vertical-align:top}
    .title-box{text-align:center;font-weight:bold;font-size:12px;padding:6px}
    .title-box .period{font-weight:normal;font-size:10px;margin-top:2px}
    .title-box .date-caption{font-size:7px;font-style:italic;color:#6b7280}
    table.items td{border:1px solid #000;padding:2px 5px}
    table.items th{border:1px solid #000;padding:3px 5px;background:#f1f5f9;font-size:9px}
    .col-particular{width:38%}
    .col-amount{width:12%;text-align:right}
    .col-comment{width:38%}
    .indent{padding-left:16px}
    .header-row td{font-weight:bold}
    .adjusted-row td{font-weight:bold;border-top:2px solid #000}
    .adjusted-row .num{text-decoration:underline}
    .missing{color:#b91c1c;font-weight:bold}
    .note{margin:6px 0;padding:5px;border:1px solid #fcd34d;background:#fffbeb;font-size:9px}
    .signatures{width:100%;margin-top:14px;border-collapse:collapse;page-break-inside:avoid}
    .signatures td{width:50%;vertical-align:top;padding:6px}
    .signatures .sig-line{text-align:center;margin-top:20px}
</style>
</head>
<body>
@php
    $fmt = function (?float $value): string {
        if ($value === null) {
            return '';
        }
        $formatted = number_format(abs($value), 2);
        return $value < 0 ? "({$formatted})" : $formatted;
    };
    $req = function (?string $value): string {
        return filled($value) ? e($value) : '<span class="missing">[NOT CONFIGURED]</span>';
    };
    $period = $report['period'];
@endphp

<div class="wrap">
    @if($report['is_draft'])
        <span class="status">DRAFT</span>
    @endif

    <table class="form">
        <tr>
            <td class="title-box">
                BANK RECONCILIATION STATEMENT
                <div class="period">For the Period {{ $period->period_start->format('F d, Y') }} to {{ $period->period_end->format('F d, Y') }}</div>
                <div class="date-caption">(Date)</div>
            </td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td style="width:50%">
                Barangay: {!! $req($report['barangay']['name']) !!}<br>
                Tel. No.: {!! $req($report['barangay']['tel_no']) !!}<br>
                Bank Name: {!! $req($report['bank']['bank_name']) !!}<br>
                Branch: {!! $req($report['bank']['branch']) !!}
            </td>
            <td style="width:50%">
                City/Municipality: {!! $req($report['barangay']['city_municipality']) !!}<br>
                Province: {!! $req($report['barangay']['province']) !!}<br>
                Current Account No.: {!! $req($report['bank']['account_number']) !!}
            </td>
        </tr>
    </table>

    @if($report['is_draft'] && $report['unresolved_amount'] != 0.0)
        <div class="note">Note: PHP {{ number_format(abs($report['unresolved_amount']), 2) }} in discrepancies remain unresolved and are not yet reflected in the adjusted balances below.</div>
    @endif

    <table class="items">
        <tr>
            <th class="col-particular">Particular</th>
            <th class="col-amount">Book</th>
            <th class="col-amount">Bank</th>
            <th class="col-comment">Explanatory Comment</th>
        </tr>
        <tr>
            <td>Unadjusted Balances</td>
            <td class="right">{{ $fmt($report['book_unadjusted']) }}</td>
            <td class="right">{{ $report['bank_unadjusted'] === null ? 'Not yet entered' : $fmt($report['bank_unadjusted']) }}</td>
            <td></td>
        </tr>
        <tr class="header-row">
            <td>Reconciling Items:</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        @foreach($report['lines'] as $line)
            @if(($line['header'] ?? false))
                <tr class="header-row">
                    <td>{{ $line['label'] }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @else
                <tr>
                    <td class="{{ ($line['indent'] ?? false) ? 'indent' : '' }}">{{ $line['label'] }}</td>
                    <td class="right">{{ $fmt($line['book']) }}</td>
                    <td class="right">{{ $fmt($line['bank']) }}</td>
                    <td>{!! nl2br(e((string) ($line['comment'] ?? ''))) !!}</td>
                </tr>
            @endif
        @endforeach
        <tr class="adjusted-row">
            <td>Adjusted Balances</td>
            <td class="right num">{{ $report['book_adjusted'] === null ? 'Not yet calculated' : $fmt($report['book_adjusted']) }}</td>
            <td class="right num">{{ $report['bank_adjusted'] === null ? 'Not yet calculated' : $fmt($report['bank_adjusted']) }}</td>
            <td></td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <strong>Prepared by:</strong>
                <div class="sig-line">_______________________<br>City/Municipal Accountant</div>
            </td>
            <td></td>
        </tr>
    </table>
</div>
</body>
</html>
