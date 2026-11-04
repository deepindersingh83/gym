@extends('public.layout')

@section('title', 'Privacy notice · '.$gym->name)

@section('content')
<div class="card legal">
    <h1>Privacy notice</h1>
    <p class="muted">{{ $gym->name }}</p>

    <p><strong>{{ $gym->name }}</strong> collects your details to run your membership. This notice explains what we collect and your rights.</p>

    <h2>What we collect and why</h2>
    <ul>
        <li><strong>Contact details</strong> (name, mobile, email, address) to manage your membership and send receipts and renewal reminders.</li>
        <li><strong>Photo</strong> to recognise you at check-in.</li>
        <li><strong>Date of birth, health questionnaire answers, medical conditions and body measurements</strong> to keep you safe while training and to track your progress. These are only seen by our staff.</li>
        <li><strong>Emergency contact</strong>, to call if something happens at the gym.</li>
        <li><strong>Payments and check-ins</strong>, for invoices, tax records and your attendance history.</li>
    </ul>
    <p>We send offers by WhatsApp or SMS only if you agreed. You can ask us to stop at any time.</p>

    <h2>Who handles it</h2>
    <p>We use {{ $app }} software to store your data securely. {{ $app }} and its service providers (messaging, payments, hosting) process it only for us. See the <a href="{{ route('legal.privacy') }}">{{ $app }} privacy policy</a> for details. We do not sell your data.</p>

    <h2>How long we keep it</h2>
    <p>While you are a member and for a reasonable time after, so you can come back. Payment records are kept as long as tax law requires.</p>

    <h2>Your rights</h2>
    <p>You can see, download, correct or ask us to erase your data, and withdraw your consent. In the member app, open <em>My details</em> to download your data or ask for it to be erased, or speak to the front desk.</p>
    @if ($isAustralia)
        <p>If you are not happy with how we handle your information, contact us first. You can also complain to the Office of the Australian Information Commissioner at <a href="https://www.oaic.gov.au">oaic.gov.au</a>.</p>
    @else
        <p>Under the Digital Personal Data Protection Act, 2023 you can also nominate someone to act for you and raise a grievance. If we do not resolve it, you can complain to the Data Protection Board of India.</p>
    @endif

    <h2>Contact</h2>
    <p>
        {{ $gym->name }}@if ($gym->address)<br>{{ collect([$gym->address, $gym->city, $gym->state, $gym->pincode])->filter()->implode(', ') }}@endif
        @if ($gym->phone)<br>Phone: {{ $gym->phone }}@endif
        @if ($gym->email)<br>Email: <a href="mailto:{{ $gym->email }}">{{ $gym->email }}</a>@endif
    </p>
</div>
<style>.legal h2 { margin-top: 24px; } .legal li { margin-bottom: 6px; }</style>
@endsection
