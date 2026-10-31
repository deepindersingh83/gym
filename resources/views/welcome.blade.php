<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gym management software that runs the front desk for you</title>
    <meta name="description" content="Members, renewals, GST invoices, WhatsApp reminders, QR check-in and owner reports for one gym or a whole chain.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07080c;
            --bg-2: #0e1018;
            --card: #12141e;
            --line: rgba(255, 255, 255, .08);
            --ink: #f4f5f8;
            --muted: #9aa0b4;
            --lime: #c6ff3d;
            --orange: #ff7a1a;
            --pink: #ff3d8b;
            --blue: #4d7cff;
            --radius: 22px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: Inter, system-ui, sans-serif; line-height: 1.6; overflow-x: hidden; }
        h1, h2, h3, .display { font-family: Sora, Inter, sans-serif; letter-spacing: -.02em; line-height: 1.08; margin: 0; }
        a { color: inherit; text-decoration: none; }
        .container { width: min(1180px, 100% - 32px); margin-inline: auto; }

        /* ---------- nav ---------- */
        .nav { position: sticky; top: 0; z-index: 50; backdrop-filter: blur(14px); background: rgba(7, 8, 12, .72); border-bottom: 1px solid var(--line); }
        .nav .container { display: flex; align-items: center; justify-content: space-between; height: 68px; gap: 16px; }
        .logo { display: flex; align-items: center; gap: 10px; font-family: Sora; font-weight: 800; font-size: 1.25rem; }
        .logo-mark { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, var(--lime), var(--orange)); display: grid; place-items: center; color: #07080c; }
        .nav-links { display: flex; gap: 28px; color: var(--muted); font-size: .95rem; }
        .nav-links a:hover { color: var(--ink); }
        .nav-cta { display: flex; gap: 10px; }
        @media (max-width: 860px) { .nav-links { display: none; } }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 999px; font-weight: 600; font-size: .95rem; transition: transform .2s, box-shadow .2s, background .2s; white-space: nowrap; }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background: var(--lime); color: #0b0c10; box-shadow: 0 10px 30px -8px rgba(198, 255, 61, .55); }
        .btn-primary:hover { box-shadow: 0 16px 40px -8px rgba(198, 255, 61, .7); }
        .btn-ghost { border: 1px solid var(--line); background: rgba(255, 255, 255, .03); }
        .btn-ghost:hover { background: rgba(255, 255, 255, .08); }
        .btn-sm { padding: 9px 16px; font-size: .88rem; }
        @media (max-width: 520px) { .nav-cta .btn-ghost { display: none; } }

        /* ---------- hero ---------- */
        .hero { position: relative; padding: 90px 0 70px; overflow: hidden; }
        .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .55; pointer-events: none; animation: float 16s ease-in-out infinite; }
        .orb-1 { width: 520px; height: 520px; background: var(--orange); top: -180px; right: -120px; }
        .orb-2 { width: 420px; height: 420px; background: var(--blue); bottom: -200px; left: -160px; animation-delay: -6s; }
        .orb-3 { width: 260px; height: 260px; background: var(--lime); top: 40%; left: 45%; opacity: .18; animation-delay: -10s; }
        @keyframes float { 50% { transform: translate(30px, -30px) scale(1.08); } }
        .grid-bg { position: absolute; inset: 0; background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px); background-size: 64px 64px; mask-image: radial-gradient(ellipse at center, #000 20%, transparent 70%); opacity: .5; }
        .hero .container { position: relative; display: grid; grid-template-columns: 1.05fr 1fr; gap: 56px; align-items: center; }
        @media (max-width: 980px) { .hero .container { grid-template-columns: 1fr; } }
        .pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px 6px 6px; border-radius: 999px; border: 1px solid var(--line); background: rgba(255, 255, 255, .04); font-size: .85rem; color: var(--muted); }
        .pill b { background: var(--lime); color: #0b0c10; border-radius: 999px; padding: 2px 10px; font-size: .75rem; }
        .hero h1 { font-size: clamp(2.6rem, 6vw, 4.6rem); font-weight: 800; margin: 22px 0 20px; }
        .hero h1 .stroke { color: transparent; -webkit-text-stroke: 1.5px var(--ink); }
        .grad { background: linear-gradient(90deg, var(--lime), var(--orange) 55%, var(--pink)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .hero p.lead { font-size: 1.15rem; color: var(--muted); max-width: 540px; margin: 0 0 30px; }
        .hero-cta { display: flex; gap: 12px; flex-wrap: wrap; }
        .trust { margin-top: 28px; display: flex; gap: 22px; flex-wrap: wrap; color: var(--muted); font-size: .9rem; }
        .trust span::before { content: "✓"; color: var(--lime); margin-right: 6px; font-weight: 700; }

        /* hero mock dashboard */
        .mock { position: relative; }
        .mock-card { background: linear-gradient(180deg, rgba(255, 255, 255, .07), rgba(255, 255, 255, .02)); border: 1px solid var(--line); border-radius: var(--radius); padding: 22px; backdrop-filter: blur(10px); box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .8); }
        .mock-main { transform: perspective(1200px) rotateY(-8deg) rotateX(4deg); transition: transform .6s; }
        .mock:hover .mock-main { transform: none; }
        .mock-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; font-size: .85rem; color: var(--muted); }
        .dots { display: flex; gap: 6px; } .dots i { width: 10px; height: 10px; border-radius: 50%; background: #2a2d3a; display: block; }
        .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
        .kpi { background: rgba(0, 0, 0, .3); border: 1px solid var(--line); border-radius: 14px; padding: 12px; }
        .kpi small { color: var(--muted); font-size: .72rem; display: block; }
        .kpi strong { font-family: Sora; font-size: 1.25rem; }
        .kpi .up { color: var(--lime); font-size: .72rem; }
        .bars { display: flex; align-items: flex-end; gap: 8px; height: 110px; padding: 12px; background: rgba(0, 0, 0, .3); border-radius: 14px; border: 1px solid var(--line); }
        .bars span { flex: 1; border-radius: 6px 6px 2px 2px; background: linear-gradient(180deg, var(--lime), rgba(198, 255, 61, .25)); transform-origin: bottom; animation: grow 1.2s cubic-bezier(.2, .8, .2, 1) both; }
        .bars span:nth-child(even) { background: linear-gradient(180deg, var(--orange), rgba(255, 122, 26, .25)); }
        @keyframes grow { from { transform: scaleY(0); } }
        .float-card { position: absolute; background: #171a26; border: 1px solid var(--line); border-radius: 16px; padding: 12px 14px; font-size: .82rem; box-shadow: 0 20px 40px -12px rgba(0, 0, 0, .7); display: flex; gap: 10px; align-items: center; animation: bob 5s ease-in-out infinite; }
        .float-card .ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 1.05rem; flex: none; }
        .float-wa { left: -34px; bottom: -26px; }
        .float-qr { right: -22px; top: -72px; animation-delay: -2.5s; }
        @keyframes bob { 50% { transform: translateY(-10px); } }
        @media (max-width: 980px) { .mock { margin-top: 64px; } .mock-main { transform: none; } .float-wa { left: 8px; } .float-qr { right: 8px; } }

        /* ---------- marquee ---------- */
        .marquee { border-block: 1px solid var(--line); background: var(--bg-2); overflow: hidden; padding: 18px 0; }
        .marquee-track { display: flex; gap: 48px; width: max-content; animation: scroll 38s linear infinite; font-family: Sora; font-weight: 600; font-size: 1.05rem; color: var(--muted); }
        .marquee-track span::after { content: "✦"; color: var(--orange); margin-left: 48px; }
        @keyframes scroll { to { transform: translateX(-50%); } }

        /* ---------- sections ---------- */
        section { padding: 110px 0; position: relative; }
        .eyebrow { color: var(--lime); font-weight: 600; letter-spacing: .14em; text-transform: uppercase; font-size: .78rem; }
        .section-head { max-width: 720px; margin-bottom: 56px; }
        .section-head h2 { font-size: clamp(2rem, 4.4vw, 3.2rem); margin: 12px 0 14px; }
        .section-head p { color: var(--muted); font-size: 1.08rem; margin: 0; }

        /* bento */
        .bento { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; }
        .tile { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 28px; position: relative; overflow: hidden; transition: border-color .3s, transform .3s; }
        .tile:hover { border-color: rgba(198, 255, 61, .35); transform: translateY(-4px); }
        .tile h3 { font-size: 1.3rem; margin: 16px 0 8px; }
        .tile p { color: var(--muted); margin: 0; font-size: .96rem; }
        .tile .icon { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font-size: 1.35rem; }
        .t-wide { grid-column: span 4; } .t-mid { grid-column: span 3; } .t-small { grid-column: span 2; }
        @media (max-width: 900px) { .t-wide, .t-mid, .t-small { grid-column: span 6; } }
        .tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
        .tags span { border: 1px solid var(--line); border-radius: 999px; padding: 4px 12px; font-size: .8rem; color: var(--muted); }
        .glow-lime { background: rgba(198, 255, 61, .12); color: var(--lime); }
        .glow-orange { background: rgba(255, 122, 26, .12); color: var(--orange); }
        .glow-pink { background: rgba(255, 61, 139, .12); color: var(--pink); }
        .glow-blue { background: rgba(77, 124, 255, .14); color: var(--blue); }
        .tile-art { position: absolute; right: -40px; bottom: -40px; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, rgba(198, 255, 61, .18), transparent 70%); }

        /* phone + reminders */
        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center; }
        @media (max-width: 900px) { .split { grid-template-columns: 1fr; } }
        .phone { width: 300px; max-width: 100%; margin-inline: auto; border-radius: 40px; padding: 14px; background: #1a1d29; border: 1px solid var(--line); box-shadow: 0 50px 100px -30px rgba(0, 0, 0, .9), 0 0 0 8px #0d0f16; }
        .phone-screen { border-radius: 28px; background: #0b141a; min-height: 470px; padding: 16px 12px; display: flex; flex-direction: column; gap: 10px; }
        .wa-top { display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255, 255, 255, .06); font-size: .85rem; }
        .wa-top .av { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--lime), var(--orange)); }
        .bubble { background: #1f2c34; border-radius: 4px 14px 14px 14px; padding: 10px 12px; font-size: .82rem; max-width: 88%; opacity: 0; transform: translateY(10px); transition: .5s; }
        .bubble.me { align-self: flex-end; background: #005c4b; border-radius: 14px 4px 14px 14px; }
        .bubble small { display: block; text-align: right; color: #8696a0; font-size: .68rem; margin-top: 4px; }
        .in .bubble { opacity: 1; transform: none; }
        .in .bubble:nth-child(3) { transition-delay: .3s; } .in .bubble:nth-child(4) { transition-delay: .9s; } .in .bubble:nth-child(5) { transition-delay: 1.5s; } .in .bubble:nth-child(6) { transition-delay: 2.1s; }
        .checklist { list-style: none; padding: 0; margin: 28px 0 0; display: grid; gap: 14px; }
        .checklist li { display: flex; gap: 14px; align-items: flex-start; }
        .checklist .num { flex: none; width: 30px; height: 30px; border-radius: 50%; background: rgba(198, 255, 61, .14); color: var(--lime); display: grid; place-items: center; font-weight: 700; font-size: .85rem; }
        .checklist b { display: block; } .checklist span { color: var(--muted); font-size: .95rem; }

        /* stats band */
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        @media (max-width: 800px) { .stats { grid-template-columns: repeat(2, 1fr); } }
        .stat { text-align: center; padding: 30px 12px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); }
        .stat .display { font-size: clamp(2.2rem, 4vw, 3rem); font-weight: 800; }
        .stat p { color: var(--muted); margin: 6px 0 0; font-size: .92rem; }

        /* roles */
        .roles { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
        .role { padding: 22px; border-radius: 18px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255, 255, 255, .04), transparent); }
        .role .em { font-size: 1.6rem; } .role h3 { font-size: 1.05rem; margin: 10px 0 6px; } .role p { color: var(--muted); font-size: .88rem; margin: 0; }

        /* steps */
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; counter-reset: s; }
        @media (max-width: 800px) { .steps { grid-template-columns: 1fr; } }
        .step { padding: 30px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); counter-increment: s; }
        .step::before { content: "0" counter(s); font-family: Sora; font-weight: 800; font-size: 3rem; color: transparent; -webkit-text-stroke: 1px rgba(198, 255, 61, .6); }
        .step h3 { margin: 6px 0 8px; font-size: 1.2rem; } .step p { color: var(--muted); margin: 0; }

        /* pricing */
        .prices { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; align-items: stretch; }
        .price { padding: 32px; border-radius: var(--radius); background: var(--card); border: 1px solid var(--line); display: flex; flex-direction: column; }
        .price.hot { background: linear-gradient(180deg, rgba(198, 255, 61, .1), var(--card) 40%); border-color: rgba(198, 255, 61, .5); position: relative; }
        .price.hot::after { content: "Most popular"; position: absolute; top: -12px; left: 32px; background: var(--lime); color: #0b0c10; font-size: .75rem; font-weight: 700; padding: 3px 12px; border-radius: 999px; }
        .price h3 { font-size: 1.2rem; } .price .amt { font-family: Sora; font-size: 2.6rem; font-weight: 800; margin: 14px 0 0; }
        .price .amt small { font-size: 1rem; color: var(--muted); font-weight: 500; }
        .price .yr { color: var(--muted); font-size: .88rem; margin-bottom: 20px; }
        .price ul { list-style: none; padding: 0; margin: 0 0 26px; display: grid; gap: 10px; flex: 1; }
        .price li { font-size: .93rem; } .price li::before { content: "✓"; color: var(--lime); font-weight: 700; margin-right: 10px; }

        /* faq */
        .faq { max-width: 820px; margin-inline: auto; display: grid; gap: 12px; }
        details { border: 1px solid var(--line); border-radius: 16px; background: var(--card); padding: 20px 24px; }
        summary { cursor: pointer; font-weight: 600; font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: "+"; color: var(--lime); font-size: 1.4rem; line-height: 1; transition: transform .25s; }
        details[open] summary::after { transform: rotate(45deg); }
        details p { color: var(--muted); margin: 12px 0 0; }

        /* final cta */
        .cta-box { position: relative; overflow: hidden; border-radius: 32px; padding: 72px 32px; text-align: center; background: radial-gradient(circle at 20% 20%, rgba(255, 122, 26, .45), transparent 45%), radial-gradient(circle at 80% 80%, rgba(77, 124, 255, .45), transparent 45%), #10121b; border: 1px solid var(--line); }
        .cta-box h2 { font-size: clamp(2rem, 5vw, 3.6rem); margin-bottom: 16px; }
        .cta-box p { color: var(--muted); font-size: 1.1rem; max-width: 560px; margin: 0 auto 30px; }

        footer { border-top: 1px solid var(--line); padding: 34px 0; color: var(--muted); font-size: .9rem; }
        footer .container { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        footer nav { display: flex; gap: 20px; flex-wrap: wrap; }

        .reveal { opacity: 0; transform: translateY(26px); transition: opacity .8s, transform .8s; }
        .reveal.in { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .reveal, .bubble { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    @php
        $register = route('filament.gym.auth.register');
        $staffLogin = route('filament.gym.auth.login');
        $memberLogin = route('filament.member.auth.login');
    @endphp

    <header class="nav">
        <div class="container">
            <a href="{{ route('home') }}" class="logo"><span class="logo-mark">⚡</span>{{ config('app.name') }}</a>
            <nav class="nav-links" aria-label="Main">
                <a href="#features">Features</a>
                <a href="#reminders">Reminders</a>
                <a href="#how">How it works</a>
                @if ($plans->isNotEmpty())<a href="#pricing">Pricing</a>@endif
                <a href="#faq">FAQ</a>
            </nav>
            <div class="nav-cta">
                <a href="{{ $staffLogin }}" class="btn btn-ghost btn-sm">Log in</a>
                <a href="{{ $register }}" class="btn btn-primary btn-sm">Start free</a>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="grid-bg"></div>
            <div class="orb orb-1"></div><div class="orb orb-2"></div><div class="orb orb-3"></div>
            <div class="container">
                <div>
                    <span class="pill"><b>NEW</b> UPI AutoPay renewals &amp; WhatsApp reminders</span>
                    <h1>Run your gym.<br><span class="stroke">Not</span> <span class="grad">spreadsheets.</span></h1>
                    <p class="lead">Members, renewals, GST invoices, dues, QR check-in and owner reports — for one gym or a whole chain. Your front desk finally gets a break.</p>
                    <div class="hero-cta">
                        <a href="{{ $register }}" class="btn btn-primary">Start your {{ $trialDays }}-day free trial →</a>
                        <a href="#features" class="btn btn-ghost">See what's inside</a>
                    </div>
                    <div class="trust">
                        <span>No card needed</span>
                        <span>Set up in 5 minutes</span>
                        <span>Cash, GPay &amp; UPI</span>
                    </div>
                </div>

                <div class="mock reveal">
                    <div class="mock-card mock-main">
                        <div class="mock-head"><div class="dots"><i></i><i></i><i></i></div><span>Owner dashboard · This month</span></div>
                        <div class="kpis">
                            <div class="kpi"><small>Revenue</small><strong>₹4.8L</strong><div class="up">▲ 18%</div></div>
                            <div class="kpi"><small>Active members</small><strong>612</strong><div class="up">▲ 34 new</div></div>
                            <div class="kpi"><small>Renewals due</small><strong>27</strong><div class="up" style="color:var(--orange)">this week</div></div>
                        </div>
                        <div class="bars" aria-hidden="true">
                            @foreach ([38, 52, 44, 66, 58, 72, 61, 84, 70, 92, 78, 100] as $i => $h)
                                <span style="height: {{ $h }}%; animation-delay: {{ $i * 60 }}ms"></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="float-card float-qr"><span class="ic glow-lime">▣</span><div><b>Riya checked in</b><br><span style="color:var(--muted)">QR · valid till 14 Dec</span></div></div>
                    <div class="float-card float-wa"><span class="ic glow-orange">✆</span><div><b>Reminder sent</b><br><span style="color:var(--muted)">WhatsApp · 3 days before</span></div></div>
                </div>
            </div>
        </section>

        <div class="marquee" aria-hidden="true">
            <div class="marquee-track">
                @foreach (array_merge(...array_fill(0, 2, ['Member profiles', 'Monthly · Quarterly · Yearly', 'GST invoices', 'Part payments & dues', 'UPI AutoPay', 'WhatsApp reminders', 'QR check-in', 'Workout & diet plans', 'Multi-branch', 'Cash closing', 'Owner reports'])) as $item)
                    <span>{{ $item }}</span>
                @endforeach
            </div>
        </div>

        <section id="features">
            <div class="container">
                <div class="section-head reveal">
                    <div class="eyebrow">Everything in one place</div>
                    <h2>Built for the way <span class="grad">real gyms</span> work.</h2>
                    <p>From the first walk-in to the hundredth renewal — every step your team does today, done faster and without the paper register.</p>
                </div>

                <div class="bento">
                    <div class="tile t-wide reveal">
                        <div class="icon glow-lime">₹</div>
                        <h3>Billing that adds itself up</h3>
                        <p>Sell a plan, take a part payment, and the balance is tracked automatically. GST invoices with CGST/SGST and receipts are one click away — as a PDF or straight to WhatsApp.</p>
                        <div class="tags"><span>Cash</span><span>GPay</span><span>UPI</span><span>Card</span><span>Razorpay links</span><span>UPI AutoPay</span><span>Day-end cash closing</span></div>
                        <div class="tile-art"></div>
                    </div>
                    <div class="tile t-small reveal">
                        <div class="icon glow-orange">🏢</div>
                        <h3>Many gyms, one login</h3>
                        <p>Run a chain with separate branches, or several gyms — switch in one click.</p>
                    </div>
                    <div class="tile t-small reveal">
                        <div class="icon glow-pink">👤</div>
                        <h3>Rich member profiles</h3>
                        <p>Photo, body stats, goals, emergency contact and a health questionnaire.</p>
                    </div>
                    <div class="tile t-small reveal">
                        <div class="icon glow-blue">▣</div>
                        <h3>QR check-in desk</h3>
                        <p>Members scan their app; staff instantly see if the plan is valid or dues are pending.</p>
                    </div>
                    <div class="tile t-small reveal">
                        <div class="icon glow-lime">📈</div>
                        <h3>Progress tracking</h3>
                        <p>Weight, BMI, body fat and measurements with charts members love to check.</p>
                    </div>
                    <div class="tile t-mid reveal">
                        <div class="icon glow-orange">🎟️</div>
                        <h3>Plans for every member</h3>
                        <p>Monthly, quarterly, half-yearly, yearly, day pass, personal training, couple and family — plus festival offers, referral and corporate coupons.</p>
                    </div>
                    <div class="tile t-mid reveal">
                        <div class="icon glow-blue">📊</div>
                        <h3>Numbers the owner actually needs</h3>
                        <p>Revenue today, this month and this year, active vs expired members, renewals due, dues outstanding and real profit after expenses. Export to Excel or PDF.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="reminders" style="background: var(--bg-2)">
            <div class="container split">
                <div class="phone reveal" id="phone">
                    <div class="phone-screen">
                        <div class="wa-top"><span class="av"></span><div><b>Your Gym</b><br><span style="color:#8696a0; font-size:.72rem">Business account</span></div></div>
                        <div style="text-align:center; color:#8696a0; font-size:.7rem">TODAY</div>
                        <div class="bubble">Hi Aarav 👋 your <b>Quarterly</b> membership ends on <b>14 Dec</b>. Renew now to keep your streak going! 💪<small>9:00 AM</small></div>
                        <div class="bubble">Pay securely here: pay.link/renew<small>9:00 AM</small></div>
                        <div class="bubble me">Done! Paid via UPI ✅<small>9:12 AM ✓✓</small></div>
                        <div class="bubble">Thanks Aarav! Renewed till <b>14 Mar</b>. Receipt: RCPT-001284 🧾<small>9:12 AM</small></div>
                    </div>
                </div>
                <div class="reveal">
                    <div class="eyebrow">Automatic reminders</div>
                    <h2 style="font-size: clamp(2rem, 4.4vw, 3.2rem); margin: 12px 0 14px">Renewals that <span class="grad">chase themselves.</span></h2>
                    <p style="color: var(--muted); font-size: 1.08rem; margin: 0">Every morning, the right message goes to the right member — and never twice.</p>
                    <ul class="checklist">
                        <li><span class="num">1</span><div><b>Before expiry</b><span>Reminders 7, 3 and 1 day before the renewal date — you choose.</span></div></li>
                        <li><span class="num">2</span><div><b>Pending dues</b><span>Polite nudges until part payments are cleared.</span></div></li>
                        <li><span class="num">3</span><div><b>Birthdays &amp; anniversaries</b><span>Small touches that keep members loyal.</span></div></li>
                        <li><span class="num">4</span><div><b>Broadcasts</b><span>Holiday hours, new batches and offers to active or lapsed members.</span></div></li>
                    </ul>
                    <div class="tags" style="margin-top: 24px"><span>WhatsApp</span><span>SMS</span><span>Email</span><span>Member app</span><span>Your own templates</span></div>
                </div>
            </div>
        </section>

        <section>
            <div class="container">
                <div class="stats">
                    <div class="stat reveal"><div class="display grad" data-count="6">0</div><p>staff roles with the right access</p></div>
                    <div class="stat reveal"><div class="display grad" data-count="9">0</div><p>plan types out of the box</p></div>
                    <div class="stat reveal"><div class="display grad" data-count="4">0</div><p>reminder channels</p></div>
                    <div class="stat reveal"><div class="display grad" data-count="{{ $trialDays }}">0</div><p>day free trial</p></div>
                </div>
            </div>
        </section>

        <section style="padding-top: 0">
            <div class="container">
                <div class="section-head reveal">
                    <div class="eyebrow">Everyone gets their own view</div>
                    <h2>The right screen for <span class="grad">every person.</span></h2>
                </div>
                <div class="roles">
                    @foreach ([
                        ['👑', 'Owner', 'Revenue, profit, every branch and the full audit trail.'],
                        ['🧭', 'Branch manager', 'Runs one branch: members, billing and staff attendance.'],
                        ['🛎️', 'Receptionist', 'Sign-ups, payments, renewals and the check-in desk.'],
                        ['🏋️', 'Trainer', 'Assigned members, measurements, workout and diet plans.'],
                        ['📱', 'Member', 'QR card, renewal date, online payment and progress.'],
                    ] as [$emoji, $title, $text])
                        <div class="role reveal"><div class="em">{{ $emoji }}</div><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="how" style="background: var(--bg-2)">
            <div class="container">
                <div class="section-head reveal">
                    <div class="eyebrow">How it works</div>
                    <h2>Live in <span class="grad">three steps.</span></h2>
                </div>
                <div class="steps">
                    <div class="step reveal"><h3>Create your gym</h3><p>Add your logo, colours, opening hours and GST number. Starter plans are created for you.</p></div>
                    <div class="step reveal"><h3>Add members &amp; staff</h3><p>Enter members at the desk or share your public sign-up page. Invite trainers and reception.</p></div>
                    <div class="step reveal"><h3>Let it run</h3><p>Payments, invoices, reminders and reports keep themselves up to date — every single day.</p></div>
                </div>
            </div>
        </section>

        @if ($plans->isNotEmpty())
            <section id="pricing">
                <div class="container">
                    <div class="section-head reveal">
                        <div class="eyebrow">Simple pricing</div>
                        <h2>Start free. <span class="grad">Grow when you're ready.</span></h2>
                        <p>Every plan starts with a {{ $trialDays }}-day free trial. No card required.</p>
                    </div>
                    <div class="prices">
                        @foreach ($plans as $plan)
                            <div class="price reveal {{ $loop->count > 1 && $loop->iteration === 2 ? 'hot' : '' }}">
                                <h3>{{ $plan->name }}</h3>
                                <div class="amt">₹{{ number_format((float) $plan->monthly_price) }}<small>/month</small></div>
                                <div class="yr">or ₹{{ number_format((float) $plan->yearly_price) }} billed yearly</div>
                                <ul>
                                    <li>{{ $plan->max_members ? number_format($plan->max_members).' members' : 'Unlimited members' }}</li>
                                    <li>{{ $plan->max_branches ? $plan->max_branches.' '.str('branch')->plural($plan->max_branches) : 'Unlimited branches' }}</li>
                                    <li>{{ $plan->max_staff ? $plan->max_staff.' staff logins' : 'Unlimited staff' }}</li>
                                    @foreach ($plan->features ?? [] as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                </ul>
                                <a href="{{ $register }}" class="btn {{ $loop->count > 1 && $loop->iteration === 2 ? 'btn-primary' : 'btn-ghost' }}" style="justify-content: center">Start free trial</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section id="faq" style="{{ $plans->isNotEmpty() ? 'background: var(--bg-2)' : '' }}">
            <div class="container">
                <div class="section-head reveal" style="margin-inline: auto; text-align: center">
                    <div class="eyebrow">FAQ</div>
                    <h2>Questions, <span class="grad">answered.</span></h2>
                </div>
                <div class="faq">
                    @foreach ([
                        'Do I need to install anything?' => 'No. It runs in the browser on any computer, tablet or phone. Members use the member app from their phone browser.',
                        'Can I manage more than one gym or branch?' => 'Yes. Add branches to one gym, or run several gyms from one login. Owners can switch between all branches or one branch at a time.',
                        'Which payment methods are supported?' => 'Cash, GPay, other UPI apps, card and bank transfer at the desk — plus online payment links and UPI AutoPay through your own Razorpay account, so money goes straight to you.',
                        'Are the invoices GST-compliant?' => 'If you add your GSTIN, invoices show CGST and SGST separately with the SAC code for fitness services. Without a GSTIN, invoices are issued without tax.',
                        'Is my gym\'s data private?' => 'Every gym\'s data is kept separate, staff only see what their role allows, and every change to payments and memberships is recorded in an audit log.',
                        'What happens after the free trial?' => 'Pick a plan to keep going. If you don\'t, your data stays safe and viewable — changes are simply paused until you continue.',
                    ] as $question => $answer)
                        <details class="reveal"><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>
                    @endforeach
                </div>
            </div>
        </section>

        <section>
            <div class="container">
                <div class="cta-box reveal">
                    <h2>Ready to <span class="grad">level up</span> your gym?</h2>
                    <p>Set up in minutes. Your first renewal reminder could go out tomorrow morning.</p>
                    <div class="hero-cta" style="justify-content: center">
                        <a href="{{ $register }}" class="btn btn-primary">Start free trial →</a>
                        <a href="{{ $memberLogin }}" class="btn btn-ghost">I'm a member</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div>© {{ now()->year }} {{ config('app.name') }}. All rights reserved.</div>
            <nav aria-label="Footer">
                <a href="{{ $staffLogin }}">Gym staff login</a>
                <a href="{{ $memberLogin }}">Member login</a>
                <a href="{{ $register }}">Create a gym</a>
                <a href="{{ route('filament.partner.auth.login') }}">Partner login</a>
            </nav>
        </div>
    </footer>

    <script>
        (() => {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const countUp = (el) => {
                const target = +el.dataset.count;
                if (reduce) { el.textContent = target; return; }
                const start = performance.now();
                const tick = (now) => {
                    const p = Math.min((now - start) / 1200, 1);
                    el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                    if (p < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
            };
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('in');
                    entry.target.querySelectorAll('[data-count]').forEach(countUp);
                    io.unobserve(entry.target);
                });
            }, { threshold: 0.15 });
            document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
        })();
    </script>
</body>
</html>
