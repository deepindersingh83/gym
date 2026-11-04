@php
    $cn = $refund->credit_note ?? [];
    $seller = $cn['seller'] ?? [];
    $buyer = $cn['buyer'] ?? [];
    $rate = (float) ($cn['tax_rate'] ?? 0);
    $inclusive = (bool) ($cn['tax_inclusive'] ?? false);
    $india = ($seller['country'] ?? ($refund->currency === 'AUD' ? 'AU' : 'IN')) === 'IN';
    $m = fn ($v) => \App\Support\Money::format((float) $v, $refund->currency);
    $pct = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $refund->credit_note_number }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; vertical-align: top; }
        .items th { background: #f3f4f6; border-bottom: 1px solid #d1d5db; font-size: 10px; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .total td { font-weight: bold; font-size: 12px; border-top: 2px solid #1f2937; }
        .brand { border-bottom: 3px solid #dc2626; padding-bottom: 10px; margin-bottom: 14px; }
        .footer { margin-top: 24px; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="brand">
        <table>
            <tr>
                <td style="padding: 0">
                    <h1>{{ $seller['legal_name'] ?? config('app.name') }}</h1>
                    <div class="muted">
                        {!! nl2br(e($seller['address'] ?? '')) !!}
                        @if (filled($seller['tax_id'] ?? null))<br>{{ $india ? 'GSTIN' : 'ABN' }}: {{ $seller['tax_id'] }}@endif
                    </div>
                </td>
                <td style="padding: 0; text-align: right">
                    <h1>{{ $rate > 0 ? ($india ? 'CREDIT NOTE' : 'ADJUSTMENT NOTE') : 'CREDIT NOTE' }}</h1>
                    <div><strong>{{ $refund->credit_note_number }}</strong></div>
                    <div class="muted">Date: {{ $refund->refunded_at->format('d M Y') }}</div>
                    <div class="muted">Against invoice {{ $cn['original_invoice'] ?? '' }} of {{ \Illuminate\Support\Carbon::parse($cn['original_date'] ?? $refund->refunded_at)->format('d M Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table style="margin-bottom: 14px">
        <tr>
            <td style="padding-left: 0">
                <div class="muted" style="font-size: 10px; text-transform: uppercase">Issued to</div>
                <strong>{{ $buyer['name'] ?? '' }}</strong><br>
                {{ $buyer['address'] ?? '' }}
                @if (filled($buyer['tax_id'] ?? null))<br>{{ $buyer['tax_id_label'] ?? 'Tax ID' }}: {{ $buyer['tax_id'] }}@endif
                @if ($india && filled($buyer['state_code'] ?? null))<br>State code: {{ $buyer['state_code'] }}@endif
            </td>
            <td class="num" style="padding-right: 0">
                <div class="muted" style="font-size: 10px; text-transform: uppercase">Reason</div>
                {{ $refund->reason }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>Description</th>@if ($india)<th>SAC</th>@endif<th class="num">Amount{{ $inclusive ? ' (incl. GST)' : '' }}</th></tr></thead>
        <tbody>
            <tr>
                <td>Refund: {{ $cn['description'] ?? 'Software subscription' }}</td>
                @if ($india)<td>{{ $cn['sac'] ?? '998314' }}</td>@endif
                <td class="num">{{ $m($inclusive ? $refund->amount : $refund->subtotal) }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 50%; margin-left: 50%; margin-top: 8px">
        @if (! $inclusive)
            <tr><td>Taxable value</td><td class="num">{{ $m($refund->subtotal) }}</td></tr>
            @if (($cn['tax_split'] ?? null) === 'cgst_sgst')
                <tr><td>CGST @ {{ $pct($rate / 2) }}%</td><td class="num">{{ $m(round($refund->tax_amount / 2, 2)) }}</td></tr>
                <tr><td>SGST @ {{ $pct($rate / 2) }}%</td><td class="num">{{ $m($refund->tax_amount - round($refund->tax_amount / 2, 2)) }}</td></tr>
            @elseif (($cn['tax_split'] ?? null) === 'igst')
                <tr><td>IGST @ {{ $pct($rate) }}%</td><td class="num">{{ $m($refund->tax_amount) }}</td></tr>
            @endif
        @endif
        <tr class="total"><td>Total refunded</td><td class="num">{{ $m($refund->amount) }}</td></tr>
        @if ($inclusive && $refund->tax_amount > 0)
            <tr><td class="muted">Includes GST of</td><td class="num muted">{{ $m($refund->tax_amount) }}</td></tr>
        @endif
    </table>

    <div class="footer">This credit note reduces the amount of the invoice shown above. Computer-generated; no signature required.</div>
</body>
</html>
