@extends('public.layout')

@section('title', config('app.name').' — gym management software')

@section('content')
    <div class="card">
        <h1>{{ config('app.name') }}</h1>
        <p class="muted" style="font-size: 1.1rem">Run one gym or a chain: members, memberships, renewals, payments, GST invoices, reminders on WhatsApp and SMS, attendance, trainers and reports, all in one place.</p>
        <p style="display: flex; gap: 12px; flex-wrap: wrap">
            <a class="btn" href="{{ route('filament.gym.auth.register') }}">Start free trial</a>
            <a class="btn secondary" href="{{ route('filament.gym.auth.login') }}">Gym staff login</a>
            <a class="btn secondary" href="{{ route('filament.member.auth.login') }}">Member login</a>
        </p>
    </div>
    <div class="grid">
        @foreach ([
            'Members & plans' => 'Profiles with photos, health questionnaire, body measurements and progress. Monthly, quarterly, yearly, day-pass, PT, couple and family plans.',
            'Billing' => 'GST invoices, part payments and dues, cash, GPay/UPI and card, Razorpay payment links and UPI AutoPay, day-end cash closing and expenses.',
            'Reminders' => 'Renewal, expiry, dues, birthday and anniversary messages on WhatsApp, SMS, email and in the member app, with your own templates.',
            'Owner dashboard' => 'Revenue today, this month and this year, active vs expired members, renewals due, profit, and Excel/PDF exports.',
        ] as $title => $text)
            <div class="card"><h2>{{ $title }}</h2><p class="muted">{{ $text }}</p></div>
        @endforeach
    </div>
@endsection
