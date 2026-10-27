@extends('pdf.layout')

@php($money = fn ($v) => \App\Support\Money::format($v, $gym->currency))

@section('title', 'Receipt '.$payment->receipt_number)

@section('heading')
    <h2>PAYMENT RECEIPT</h2>
    <div><strong>{{ $payment->receipt_number }}</strong></div>
    <div class="muted">{{ $payment->paid_at->format('d M Y, h:i A') }}</div>
@endsection

@section('content')
    <table class="items">
        <tr><td class="muted" style="width: 40%">Received from</td><td><strong>{{ $payment->member->name }}</strong> ({{ $payment->member->member_code }})</td></tr>
        <tr><td class="muted">Phone</td><td>{{ $payment->member->phone }}</td></tr>
        @if ($payment->subscription)
            <tr><td class="muted">For</td><td>{{ $payment->subscription->plan?->name }} membership · invoice {{ $payment->subscription->invoice_number }}</td></tr>
            <tr><td class="muted">Membership period</td><td>{{ $payment->subscription->start_date->format('d M Y') }} to {{ $payment->subscription->end_date->format('d M Y') }}</td></tr>
        @endif
        <tr><td class="muted">Payment mode</td><td>{{ $payment->method->getLabel() }}@if ($payment->reference) · Ref: {{ $payment->reference }}@endif</td></tr>
        <tr><td class="muted">Received by</td><td>{{ $payment->receivedBy?->name ?? 'Online' }}</td></tr>
        <tr class="total"><td>Amount received</td><td>{{ $money($payment->amount) }}</td></tr>
        @if ($payment->subscription && $payment->subscription->balance_due > 0)
            <tr><td class="muted">Balance still due</td><td><span class="badge due">{{ $money($payment->subscription->balance_due) }}</span></td></tr>
        @endif
    </table>
@endsection
