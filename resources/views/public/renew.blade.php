@extends('public.layout')

@section('title', 'Renew · '.$gym->name)

@section('content')
<div class="card" style="text-align: center">
    @if ($gym->logo_url)<img src="{{ $gym->logo_url }}" alt="" style="width: 72px; height: 72px; object-fit: contain; border-radius: 12px">@endif
    <h1 style="margin-top: 8px">Renew at {{ $gym->name }}</h1>
    <p class="muted">Hi {{ \Illuminate\Support\Str::of($member->name)->before(' ') }}, we'd love to see you back.@if ($plan) Your plan: <strong>{{ $plan->name }}</strong> ({{ \App\Support\Money::format($plan->price, $gym->currency) }}).@endif</p>
    <p>Renew at the front desk, or message us and we'll get you sorted.</p>
    <p style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap">
        @if ($gym->phone)
            <a class="btn" href="https://wa.me/{{ \App\Services\Messaging\PhoneNumber::international($gym->phone, \App\Support\Country::get($gym->country, 'phone_code')) }}?text={{ rawurlencode('Hi, I would like to renew my membership ('.$member->member_code.').') }}">WhatsApp us</a>
            <a class="btn secondary" href="tel:{{ $gym->phone }}">Call {{ $gym->phone }}</a>
        @endif
    </p>
</div>
@endsection
