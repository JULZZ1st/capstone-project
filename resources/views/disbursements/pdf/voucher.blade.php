<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disbursement Voucher</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 10px; }
        .page { width: 80%; margin: 0 auto; }
        .annex { color: #808080; text-align: right; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { border: 1px solid #000; padding: 5px 6px; vertical-align: top; }
        .title { font-weight: bold; text-align: center; font-size: 13px; vertical-align: middle; }
        .center { text-align: center; }
        .field { min-height: 28px; }
        .particulars { height: 128px; }
        .particulars td { vertical-align: top; }
        .amount { text-align: right; white-space: nowrap; }
        .certification td { width: 33.333%; height: 205px; }
        .certification p { margin: 9px 0; text-align: justify; }
        .signature { margin-top: 35px; text-align: center; }
        .signature-line { display: inline-block; width: 135px; border-top: 1px solid #000; padding-top: 3px; }
        .small { font-size: 8px; }
        .accounting-heading td { font-weight: bold; height: 24px; }
        .accounting th, .accounting td { text-align: center; height: 27px; vertical-align: middle; }
        .received td { height: 83px; }
        .received .signature { margin-top: 30px; }
        .received-payment { font-weight: bold; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
<div class="page">
    <div class="annex">Annex 12</div>

    <table>
        <tr>
            <td class="title" style="width:60%">Disbursement Voucher</td>
            <td style="width:15%">DV No. :<br>{{ $disbursement->dv_number ?: '' }}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width:30%">Barangay:<br>{{ $disbursement->barangay ?: ($barangayProfile['location_barangay'] ?? $barangayProfile['name'] ?? '') }}</td>
            <td style="width:30%">City/Municipality:<br>{{ $disbursement->city_municipality ?: ($barangayProfile['city_municipality'] ?? '') }}</td>
            <td style="width:15%">Date :<br>{{ ($disbursement->requested_date ?? $disbursement->created_at)?->format('m/d/Y') }}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width:30%">Payee:<br>{{ $disbursement->recipient }}</td>
            <td style="width:30%">Province:<br>{{ $disbursement->province ?: ($barangayProfile['province'] ?? '') }}</td>
            <td style="width:15%">Fund:<br>{{ $disbursement->fund_source ?: '' }}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width:30%">Address:<br>{{ $disbursement->payee_address ?: '' }}</td>
            <td style="width:30%">TIN :<br>{{ $disbursement->payee_tin ?: '' }}</td>
            <td style="width:15%">ObR No.:<br>{{ $disbursement->obr_number ?: '' }}</td>
        </tr>
    </table>
    <table>
        <tr>
            <td class="title" style="width:60%; font-size:10px">Particulars</td>
            <td class="title" style="width:15%; font-size:10px">Amount</td>
        </tr>
    </table>
    <table class="particulars">
        <tr>
            <td style="width:60%">{{ $disbursement->purpose }}@if($disbursement->notes)<br><br>{{ $disbursement->notes }}@endif</td>
            <td class="amount" style="width:15%">{{ number_format((float) $disbursement->amount, 2) }}</td>
        </tr>
    </table>

    <table class="certification">
        <tr>
            <td>
                <b>A. Certified:</b>
                <p>As to availability of appropriation</p>
                <p>As to obligation of appropriation</p>
                <div class="signature"><span class="signature-line"> </span><br><span class="small">(Signature Over Printed Name)</span><br><br>Chairman, Committee on Appropriation</div>
                <br>Date:_____________
            </td>
            <td>
                <b>B. Certified:</b>
                <p>As to availability of funds</p>
                <p>As to completeness and propriety of supporting documents</p>
                <div class="signature"><span class="signature-line"> </span><br><span class="small">(Signature Over Printed Name)</span><br><br>Barangay Treasurer</div>
                <br>Date:_____________
            </td>
            <td>
                <b>C. Certified:</b>
                <p>As to validity, propriety, and legality of claim</p>
                <p><b>Approved for Payment:</b></p>
                <div class="signature"><span class="signature-line"> </span><br><span class="small">(Signature Over Printed Name)</span><br><br>Punong Barangay</div>
                <br>Date:_____________
            </td>
        </tr>
    </table>

    <table class="accounting-heading"><tr><td>D. Accounting Entries</td></tr></table>
    <table class="accounting">
        <tr><th style="width:40%">Account</th><th style="width:15%">Account Code</th><th style="width:10%">Debit</th><th style="width:15%">Credit</th></tr>
        <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    </table>

    <table class="received">
        <tr>
            <td style="width:40%"><span class="received-payment">E. Received Payment:</span><div class="signature"><span class="signature-line" style="width:160px"> </span><br>Signature Over Printed Name</div></td>
            <td style="width:25%">Check No.: _____________<br>Bank Name:____________<br>OR No.: _______________</td>
            <td style="width:15%; text-align:center">Date: _______</td>
        </tr>
    </table>
</div>
</body>
</html>
