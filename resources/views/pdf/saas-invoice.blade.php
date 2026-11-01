@php
    $inv = $payment->invoice ?? [];
    $seller = $inv['seller'] ?? [];
    $buyer = $inv['buyer'] ?? [];
    $line = $inv['line'] ?? [];
    $rate = (float) ($inv['tax_rate'] ?? 0);
    $inclusive = (bool) ($inv['tax_inclusive'] ?? false);
    $india = ($seller['country'] ?? 'IN') === 'IN';
    $cur = $payment->currency;
    $m = fn ($v) => \App\Support\Money::format((float) $v, $cur);
    $registered = filled($seller['tax_id'] ?? null) && $rate > 0;
    $listPrice = (float) ($line['list_price'] ?? 0);
    $credit = (float) ($line['credit'] ?? 0);
    $discount = (float) ($line['discount'] ?? 0);
    // Payments recorded by hand may have no price breakdown: show the taxable value as the line amount.
    $lineAmount = $listPrice > 0 ? $listPrice : ($inclusive ? (float) $payment->amount : (float) $payment->subtotal);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->invoice_number }}</title>
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
        .brand { border-bottom: 3px solid #f59e0b; padding-bottom: 10px; margin-bottom: 14px; }
        .paid { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; background: #dcfce7; color: #166534; }
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
                    <h1>{{ $registered ? 'TAX INVOICE' : 'INVOICE' }}</h1>
                    <div><strong>{{ $payment->invoice_number }}</strong></div>
                    <div class="muted">Date: {{ $payment->paid_at->format('d M Y') }}</div>
                    <div style="margin-top: 4px"><span class="paid">PAID</span></div>
                </td>
            </tr>
        </table>
    </div>

    <table style="margin-bottom: 14px">
        <tr>
            <td style="width: 55%; padding-left: 0">
                <div class="muted" style="font-size: 10px; text-transform: uppercase">Billed to</div>
                <strong>{{ $buyer['name'] ?? $payment->gym->name }}</strong><br>
                {{ $buyer['address'] ?? '' }}
                @if (filled($buyer['tax_id'] ?? null))<br>{{ $buyer['tax_id_label'] ?? 'Tax ID' }}: {{ $buyer['tax_id'] }}@endif
                @if ($india && filled($buyer['state_code'] ?? null))<br>State code: {{ $buyer['state_code'] }}@endif
            </td>
            <td class="num" style="padding-right: 0">
                @if ($payment->period_start)
                    <div class="muted" style="font-size: 10px; text-transform: uppercase">Service period</div>
                    {{ $payment->period_start->format('d M Y') }} to {{ $payment->period_end?->copy()->subDay()->format('d M Y') }}
                @endif
                @if ($payment->reference)<div class="muted" style="margin-top: 4px">Payment ref: {{ $payment->reference }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                @if ($india)<th>SAC</th>@endif
                <th class="num">Amount{{ $inclusive ? ' (incl. GST)' : '' }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $line['description'] ?? 'Software subscription' }}</td>
                @if ($india)<td>{{ $inv['sac'] ?? '998314' }}</td>@endif
                <td class="num">{{ $m($lineAmount) }}</td>
            </tr>
            @if ($credit > 0)
                <tr><td>Less: credit for unused time on previous plan</td>@if ($india)<td></td>@endif<td class="num">−{{ $m($credit) }}</td></tr>
            @endif
            @if ($discount > 0)
                <tr><td>Less: discount{{ filled($line['coupon'] ?? null) ? ' ('.$line['coupon'].')' : '' }}</td>@if ($india)<td></td>@endif<td class="num">−{{ $m($discount) }}</td></tr>
            @endif
        </tbody>
    </table>

    <table style="width: 50%; margin-left: 50%; margin-top: 8px">
        @if (! $inclusive)
            <tr><td>Taxable value</td><td class="num">{{ $m($payment->subtotal) }}</td></tr>
            @if (($inv['tax_split'] ?? null) === 'cgst_sgst')
                <tr><td>CGST @ {{ rtrim(rtrim(number_format($rate / 2, 2), '0'), '.') }}%</td><td class="num">{{ $m($payment->tax_amount / 2) }}</td></tr>
                <tr><td>SGST @ {{ rtrim(rtrim(number_format($rate / 2, 2), '0'), '.') }}%</td><td class="num">{{ $m($payment->tax_amount - round($payment->tax_amount / 2, 2)) }}</td></tr>
            @elseif (($inv['tax_split'] ?? null) === 'igst')
                <tr><td>IGST @ {{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%</td><td class="num">{{ $m($payment->tax_amount) }}</td></tr>
            @endif
        @endif
        <tr class="total"><td>Total paid</td><td class="num">{{ $m($payment->amount) }}</td></tr>
        @if ($inclusive && $payment->tax_amount > 0)
            <tr><td class="muted">Total includes GST of</td><td class="num muted">{{ $m($payment->tax_amount) }}</td></tr>
        @endif
    </table>

    <div class="footer">
        @if (! $registered)
            {{ $india ? 'Supplier not registered under GST.' : 'No GST has been charged.' }}<br>
        @endif
        This is a computer-generated invoice. Thank you for using {{ config('app.name') }}.
    </div>
</body>
</html>
