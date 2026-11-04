@extends('public.layout')

@section('title', 'Privacy policy · '.$app)

@section('content')
<div class="card legal">
    <h1>Privacy policy</h1>
    <p class="muted">{{ $app }} · Last updated {{ $updated }}</p>

    <p>{{ $app }} is gym management software run by {{ $company }}. This policy explains what personal data we handle, why, and your rights. It covers gym owners and staff who use the software, gym members who use the member app or join online, and visitors to our website.</p>

    <h2>Who is responsible</h2>
    <p><strong>Gyms decide what member data they collect and why.</strong> For members' data, each gym is the <em>data fiduciary</em> (India) or the organisation that holds the information (Australia). {{ $app }} processes it on the gym's behalf and only on its instructions. For questions about your membership data, contact your gym first; each gym's notice is linked from its join page.</p>
    <p>{{ $company }} is responsible for the data of gym owners and staff who sign up for {{ $app }}, and for billing.</p>

    <h2>What we collect</h2>
    <ul>
        <li><strong>Account details:</strong> name, email, mobile number, password (stored hashed), and sign-in records.</li>
        <li><strong>Gym records entered by gyms:</strong> member names, contact details, date of birth, photo, membership and payment history, check-ins, body measurements, health questionnaire answers, and emergency contacts.</li>
        <li><strong>Billing:</strong> the gym's business details (GSTIN or ABN, address) and payment records. Card and UPI details are handled by Razorpay or Stripe; we never see or store full card numbers.</li>
        <li><strong>Messages:</strong> the text and delivery status of SMS, WhatsApp and email messages sent through the software.</li>
        <li><strong>Technical data:</strong> IP address, browser and error logs, kept to keep the service secure and working.</li>
    </ul>

    <h2>Why we use it</h2>
    <ul>
        <li>To provide the software: memberships, invoices, reminders, check-in, the member app and the gym's website.</li>
        <li>To bill gyms and keep tax records required by law.</li>
        <li>To send service messages (sign-in codes, renewal reminders, receipts). Gyms send marketing messages to members only if the member has agreed.</li>
        <li>To keep the service secure, prevent fraud and fix problems.</li>
    </ul>
    <p>We do not sell personal data, and we do not use members' data for our own marketing.</p>

    <h2>Health information</h2>
    <p>Health questionnaire answers, medical conditions and body measurements are sensitive. They are collected only with the member's consent, are visible only to the gym's staff, and can be erased on request.</p>

    <h2>Who we share it with</h2>
    <p>Only with service providers who help us run {{ $app }}: hosting, email delivery, SMS and WhatsApp providers (such as MSG91 and Meta), payment processors (Razorpay, Stripe) and, if the gym turns it on, Facebook/Instagram lead ads. They may only use the data to provide their service. We also share data when the law requires it.</p>
    <p>Data is stored on servers chosen by {{ $company }}. Some providers (for example email and WhatsApp) may process data outside your country under their own safeguards.</p>

    <h2>How long we keep it</h2>
    <ul>
        <li>Gym records are kept while the gym uses {{ $app }}. When a gym closes its account, everything is deleted for good after 30 days.</li>
        <li>Invoices and payment records are kept for the period tax law requires (in India, at least 8 years under the CGST Act; in Australia, 5 years).</li>
        <li>When a member asks to be erased, their personal details, photo, health answers and measurements are removed; payment records stay without their name.</li>
    </ul>

    <h2>Your rights</h2>
    <p>You can ask to <strong>see</strong> your data, <strong>correct</strong> it, <strong>withdraw consent</strong> and have it <strong>erased</strong>. Members can download their data and request erasure from the member app (My details), or ask their gym. Gym owners can download all their gym's data at any time.</p>

    <h3>India (Digital Personal Data Protection Act, 2023)</h3>
    <p>You may access a summary of your data, correct or erase it, withdraw consent as easily as you gave it, nominate someone to exercise your rights if you die or become unable to, and raise a grievance. Contact our grievance officer{{ $grievanceName ? ', '.$grievanceName.',' : '' }} at <a href="mailto:{{ $contact }}">{{ $contact }}</a>. We reply within 30 days. If you are not satisfied, you can complain to the Data Protection Board of India.</p>
    @if ($india['legal_name'])<p class="muted">{{ $india['legal_name'] }}@if (! empty($india['address'])), {{ $india['address'] }}@endif</p>@endif

    <h3>Australia (Privacy Act 1988 and the Australian Privacy Principles)</h3>
    <p>You may access and correct the personal information held about you, and complain about how it is handled. Contact us at <a href="mailto:{{ $contact }}">{{ $contact }}</a>; we reply within 30 days. If you are not satisfied, you can complain to the Office of the Australian Information Commissioner at <a href="https://www.oaic.gov.au">oaic.gov.au</a>. If a data breach is likely to cause you serious harm, we and the gym will tell you and the OAIC.</p>
    @if ($australia['legal_name'])<p class="muted">{{ $australia['legal_name'] }}@if (! empty($australia['tax_id'])) · ABN {{ $australia['tax_id'] }}@endif</p>@endif

    <h2>Security</h2>
    <p>Data is sent over HTTPS. Passwords are hashed, and payment and messaging keys are stored encrypted. Each gym can only see its own data. Our admin team signs in with two-step login, and their actions are logged.</p>

    <h2>Children</h2>
    <p>Gyms must get a parent's or guardian's consent before adding a member under 18.</p>

    <h2>Changes</h2>
    <p>We will post changes here and tell gym owners by email about important ones.</p>

    <p><a href="{{ route('legal.terms') }}">Terms of service</a> · <a href="{{ route('home') }}">{{ $app }} home</a></p>
</div>
<style>.legal h2 { margin-top: 28px; } .legal h3 { margin: 18px 0 6px; font-size: 1.05rem; } .legal li { margin-bottom: 6px; }</style>
@endsection
