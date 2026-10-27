@extends('pdf.layout')

@php($money = fn ($v) => \App\Support\Money::format($v, $gym->currency))

@section('title', 'Outstanding dues')

@section('heading')
    <h2>OUTSTANDING DUES</h2>
    <div class="muted">As of {{ now()->format('d M Y, h:i A') }}</div>
@endsection

@section('content')
    <table class="items">
        <thead>
            <tr><th>Member</th><th>Phone</th><th>Invoice</th><th>Plan</th><th>Ends</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Due</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->member?->name }}</td>
                    <td>{{ $row->member?->phone }}</td>
                    <td>{{ $row->invoice_number }}</td>
                    <td>{{ $row->plan?->name }}</td>
                    <td>{{ $row->end_date->format('d M Y') }}</td>
                    <td class="num">{{ $money($row->total_amount) }}</td>
                    <td class="num">{{ $money($row->amount_paid) }}</td>
                    <td class="num">{{ $money($row->balance_due) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="7" class="num">Total outstanding ({{ $rows->count() }} memberships)</td>
                <td class="num">{{ $money($rows->sum('balance_due')) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
