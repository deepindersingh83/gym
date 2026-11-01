@extends('pdf.layout')

@php
    $money = fn ($v) => \App\Support\Money::format($v, $gym->currency);
    $member = $subscription->member;
    // India: GST added and split into CGST + SGST, with SAC codes. Australia: prices include GST.
    $india = $gym->country !== 'AU';
    $sac = $india && $gym->gstin;
    $cols = $sac ? 2 : 1;
@endphp

@section('title', 'Invoice '.$subscription->invoice_number)

@section('heading')
    <h2>{{ $gym->isGstRegistered() ? 'TAX INVOICE' : 'INVOICE' }}</h2>
    <div><strong>{{ $subscription->invoice_number }}</strong></div>
    <div class="muted">Date: {{ $subscription->created_at->format('d M Y') }}</div>
    <div style="margin-top: 6px">
        @if ($subscription->balance_due > 0)
            <span class="badge due">DUE {{ $money($subscription->balance_due) }}</span>
        @else
            <span class="badge paid">PAID</span>
        @endif
    </div>
@endsection

@section('content')
    <table style="margin-bottom: 14px">
        <tr>
            <td style="width: 50%">
                <div class="muted">Billed to</div>
                <strong>{{ $member->name }}</strong> ({{ $member->member_code }})<br>
                {{ $member->phone }}@if ($member->email) · {{ $member->email }}@endif
                @if ($member->address)<br>{{ $member->address }}@endif
            </td>
            <td>
                <div class="muted">Membership</div>
                <strong>{{ $subscription->plan->name }}</strong><br>
                {{ $subscription->start_date->format('d M Y') }} to {{ $subscription->end_date->format('d M Y') }}
                @if ($subscription->additionalMembers->isNotEmpty())
                    <br>Also covers: {{ $subscription->additionalMembers->pluck('name')->implode(', ') }}
                @endif
                @if ($sac)<br><span class="muted">Place of supply: {{ $gym->state }} {{ $gym->state_code ? '('.$gym->state_code.')' : '' }}</span>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                @if ($sac)<th>SAC</th>@endif
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $subscription->plan->name }} membership ({{ $subscription->plan->duration_label }})</td>
                @if ($sac)<td>999723</td>@endif
                <td class="num">{{ $money($subscription->plan_price) }}</td>
            </tr>
            @if ($subscription->discount_amount > 0)
                <tr>
                    <td>Discount{{ $subscription->coupon ? ' ('.$subscription->coupon->code.')' : '' }}</td>
                    @if ($sac)<td></td>@endif
                    <td class="num">− {{ $money($subscription->discount_amount) }}</td>
                </tr>
            @endif
            @if ($subscription->admission_fee > 0)
                <tr>
                    <td>Admission fee</td>
                    @if ($sac)<td>999723</td>@endif
                    <td class="num">{{ $money($subscription->admission_fee) }}</td>
                </tr>
            @endif
            @if ($india)
            <tr>
                <td class="num" colspan="{{ $cols }}">Taxable value</td>
                <td class="num">{{ $money($subscription->taxable_amount) }}</td>
            </tr>
            @endif
            @if ($india && $subscription->gst_rate > 0)
                <tr>
                    <td class="num" colspan="2">CGST @ {{ (float) $subscription->gst_rate / 2 }}%</td>
                    <td class="num">{{ $money($subscription->cgst_amount) }}</td>
                </tr>
                <tr>
                    <td class="num" colspan="2">SGST @ {{ (float) $subscription->gst_rate / 2 }}%</td>
                    <td class="num">{{ $money($subscription->sgst_amount) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td class="num" colspan="{{ $cols }}">Total</td>
                <td class="num">{{ $money($subscription->total_amount) }}</td>
            </tr>
            @if (! $india && (float) $subscription->tax_amount > 0)
                <tr>
                    <td class="num muted" colspan="{{ $cols }}">Total includes GST of</td>
                    <td class="num muted">{{ $money($subscription->tax_amount) }}</td>
                </tr>
            @endif
            <tr>
                <td class="num" colspan="{{ $cols }}">Paid</td>
                <td class="num">{{ $money($subscription->amount_paid) }}</td>
            </tr>
            <tr>
                <td class="num" colspan="{{ $cols }}"><strong>Balance due</strong></td>
                <td class="num"><strong>{{ $money($subscription->balance_due) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if ($subscription->payments->isNotEmpty())
        <h2 style="margin-top: 18px">Payments</h2>
        <table class="items">
            <thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th>Reference</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($subscription->payments as $payment)
                    <tr>
                        <td>{{ $payment->receipt_number }}</td>
                        <td>{{ $payment->paid_at->format('d M Y') }}</td>
                        <td>{{ $payment->method->getLabel() }}</td>
                        <td>{{ $payment->reference ?? '—' }}</td>
                        <td class="num">{{ $money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
