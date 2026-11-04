@extends('public.layout')

@section('title', 'Terms of service · '.$app)

@section('content')
<div class="card legal">
    <h1>Terms of service</h1>
    <p class="muted">{{ $app }} · Last updated {{ $updated }}</p>

    <p>These terms are an agreement between {{ $company }} ("we") and the gym business that signs up for {{ $app }} ("you"). By creating a gym account you accept them.</p>

    <h2>1. The service</h2>
    <p>{{ $app }} lets you manage members, memberships, payments, invoices, check-ins, staff, messages and a gym website. Features depend on your plan. We may improve or change features; we will not remove a core feature you pay for during a paid period without notice.</p>

    <h2>2. Free trial and plans</h2>
    <p>New gyms get a free trial. After it, you need a paid plan to keep making changes; you can always view and download your data.</p>

    <h2>3. Payment, renewals and GST</h2>
    <ul>
        <li>Plans are billed monthly or yearly in advance, in Indian rupees (gyms in India, plus 18% GST) or Australian dollars (gyms in Australia, GST included).</li>
        <li>With auto-renew on, we charge your card or UPI mandate at the end of each period until you turn it off on the Plan &amp; billing page.</li>
        <li>If a payment fails or is late, you keep full access for the grace period shown in your panel; after that, changes are locked until you pay.</li>
        <li>We issue a tax invoice for every payment and a credit note for every refund.</li>
        <li>Prices may change; changes apply from your next renewal and we will tell you at least 30 days before.</li>
    </ul>

    <h2>4. Refunds</h2>
    <p>Payments are not refundable except where we agree in writing or the law requires (including the Australian Consumer Law, which these terms do not limit). If we refund a plan payment, the paid period is usually shortened to match.</p>

    <h2>5. Your data and your members' data</h2>
    <ul>
        <li>You own your gym's data. You can download all of it at any time from <em>Setup → Your data</em>.</li>
        <li>You are responsible for having a lawful reason and, where needed, consent for the member data you enter (including health information and photos), and for answering members' privacy requests. The software gives you tools for consent, download and erasure.</li>
        <li>We process member data only to provide the service, as described in our <a href="{{ route('legal.privacy') }}">privacy policy</a>.</li>
    </ul>

    <h2>6. Messages</h2>
    <p>You must only send marketing SMS or WhatsApp messages to members who have agreed, and follow telecom rules (in India, TRAI DLT registration; in Australia, the Spam Act 2003). Message credits are prepaid and not refundable once used.</p>

    <h2>7. Acceptable use</h2>
    <p>Do not use {{ $app }} for anything unlawful, to send spam, to access another gym's data, or to disrupt the service. Keep your passwords safe; you are responsible for what your staff do with their logins.</p>

    <h2>8. Suspension and closing your account</h2>
    <p>We may suspend changes if payment is overdue or these terms are seriously broken; we will tell you why. You may close your account at any time from <em>Setup → Your data</em>. Your data is kept for 30 days in case you change your mind, then deleted for good, except records we must keep for tax.</p>

    <h2>9. Availability and liability</h2>
    <p>We work to keep {{ $app }} available and your data backed up, but cannot promise it will never be interrupted. To the extent the law allows, our total liability in any 12 months is limited to what you paid us in that time, and we are not liable for indirect losses such as lost profits. Nothing here limits rights you have under consumer protection law that cannot be excluded.</p>

    <h2>10. Law</h2>
    <p>For gyms in India, these terms are governed by the laws of India. For gyms in Australia, by the laws of the Australian state or territory where the gym is based.</p>

    <h2>11. Contact</h2>
    <p><a href="mailto:{{ $contact }}">{{ $contact }}</a></p>

    <p><a href="{{ route('legal.privacy') }}">Privacy policy</a> · <a href="{{ route('home') }}">{{ $app }} home</a></p>
</div>
<style>.legal h2 { margin-top: 28px; } .legal li { margin-bottom: 6px; }</style>
@endsection
