@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Storage;

    $brand = $gym->primary_color ?: '#f59e0b';
    $tagline = $gym->websiteSetting('tagline') ?: 'Train harder. Feel better. Live stronger.';
    $about = $gym->websiteSetting('about');
    $place = collect([$gym->city, $gym->state])->filter()->implode(', ');
    $description = $gym->websiteSetting('seo_description')
        ?: \Illuminate\Support\Str::limit(trim($gym->name.($place ? " in {$place}" : '').'. '.($about ?: $tagline)), 158);
    $title = $gym->name.($gym->city ? ' — Gym in '.$gym->city : ' — Gym');
    $hours = collect($gym->opening_hours ?? []);
    $todayName = strtolower(now($gym->timezone)->format('l'));
    $today = $hours->firstWhere('day', $todayName);
    $whatsapp = $gym->whatsapp ?: $gym->phone;
    $socials = collect(['instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'])
        ->filter(fn ($label, $key) => filled($gym->websiteSetting($key)));
    $joinUrl = route('join.show', $gym);
    $time = fn ($t) => $t ? Carbon::parse($t)->format('g:i A') : '';
    $dayIndex = ['monday' => 'Mo', 'tuesday' => 'Tu', 'wednesday' => 'We', 'thursday' => 'Th', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'Su'];

    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ExerciseGym',
        'name' => $gym->name,
        'description' => $description,
        'url' => $canonical,
        'telephone' => $gym->phone,
        'email' => $gym->email,
        'image' => $cover ?? $gym->logo_url,
        'logo' => $gym->logo_url,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $gym->address,
            'addressLocality' => $gym->city,
            'addressRegion' => $gym->state,
            'postalCode' => $gym->pincode,
            'addressCountry' => $gym->currency === 'AUD' ? 'AU' : 'IN',
        ]),
        'openingHoursSpecification' => $hours->reject(fn ($h) => $h['closed'] ?? false)->map(fn ($h) => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ucfirst($h['day']),
            'opens' => substr((string) ($h['opens'] ?? ''), 0, 5),
            'closes' => substr((string) ($h['closes'] ?? ''), 0, 5),
        ])->values()->all(),
        'sameAs' => $socials->keys()->map(fn ($k) => $gym->websiteSetting($k))->values()->all(),
    ]);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($cover ?? $gym->logo_url)<meta property="og:image" content="{{ $cover ?? $gym->logo_url }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    @if ($gym->logo_url)<link rel="icon" href="{{ $gym->logo_url }}">@endif
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --brand: {{ $brand }}; --ink: #12131a; --muted: #5f6475; --line: #e7e8ee; --bg: #ffffff; --soft: #f6f7fa; --radius: 18px; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.6; }
        h1, h2, h3 { font-family: Sora, Inter, sans-serif; letter-spacing: -.02em; line-height: 1.12; margin: 0; }
        a { color: inherit; }
        img { max-width: 100%; display: block; }
        .container { width: min(1120px, 100% - 32px); margin-inline: auto; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 13px 22px; border-radius: 999px; font-weight: 600; text-decoration: none; border: 0; cursor: pointer; font: inherit; font-weight: 600; transition: transform .2s, box-shadow .2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-brand { background: var(--brand); color: #fff; box-shadow: 0 10px 24px -10px var(--brand); }
        .btn-light { background: rgba(255,255,255,.14); color: #fff; border: 1px solid rgba(255,255,255,.35); backdrop-filter: blur(6px); }
        .btn-outline { background: #fff; color: var(--ink); border: 1px solid var(--line); }

        /* nav */
        .nav { position: sticky; top: 0; z-index: 30; background: rgba(255,255,255,.9); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .nav .container { display: flex; align-items: center; justify-content: space-between; height: 66px; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: Sora; font-weight: 800; font-size: 1.15rem; text-decoration: none; }
        .brand img { width: 38px; height: 38px; object-fit: contain; border-radius: 10px; }
        .brand .mark { width: 38px; height: 38px; border-radius: 10px; background: var(--brand); color: #fff; display: grid; place-items: center; }
        .nav-links { display: flex; gap: 24px; font-size: .95rem; color: var(--muted); }
        .nav-links a { text-decoration: none; } .nav-links a:hover { color: var(--ink); }
        @media (max-width: 860px) { .nav-links { display: none; } }

        /* hero */
        .hero { position: relative; color: #fff; min-height: 560px; display: flex; align-items: flex-end; overflow: hidden;
            background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 85%, #000), #0d0e14 70%); }
        .hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center; }
        .hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.15), rgba(0,0,0,.75)); }
        .hero .container { position: relative; z-index: 1; padding: 120px 0 64px; }
        .hero h1 { font-size: clamp(2.3rem, 6vw, 4.2rem); font-weight: 800; max-width: 820px; }
        .hero p { font-size: 1.15rem; max-width: 620px; opacity: .9; margin: 16px 0 28px; }
        .hero-cta { display: flex; gap: 12px; flex-wrap: wrap; }
        .chips { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 28px; }
        .chip { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); border-radius: 999px; padding: 6px 14px; font-size: .9rem; backdrop-filter: blur(6px); }
        .chip.open::before { content: "●"; color: #4ade80; margin-right: 6px; }
        .chip.closed::before { content: "●"; color: #f87171; margin-right: 6px; }

        section { padding: 84px 0; }
        .soft { background: var(--soft); }
        .eyebrow { color: var(--brand); font-weight: 700; letter-spacing: .12em; text-transform: uppercase; font-size: .78rem; }
        .head { max-width: 680px; margin-bottom: 40px; }
        .head h2 { font-size: clamp(1.8rem, 4vw, 2.6rem); margin: 10px 0 10px; }
        .head p { color: var(--muted); margin: 0; }

        .about { display: grid; grid-template-columns: 1.2fr 1fr; gap: 48px; align-items: center; }
        @media (max-width: 860px) { .about { grid-template-columns: 1fr; } }
        .about p { color: var(--muted); font-size: 1.05rem; white-space: pre-line; }
        .about img { border-radius: var(--radius); aspect-ratio: 4/3; object-fit: cover; width: 100%; }

        .plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
        .plan { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 26px; display: flex; flex-direction: column; gap: 6px; transition: border-color .2s, transform .2s; }
        .plan:hover { border-color: var(--brand); transform: translateY(-3px); }
        .plan h3 { font-size: 1.1rem; }
        .plan .price { font-family: Sora; font-size: 2rem; font-weight: 800; }
        .plan .per { color: var(--muted); font-size: .9rem; margin-top: -4px; }
        .plan p { color: var(--muted); margin: 0; font-size: .93rem; flex: 1; }
        .plan .btn { margin-top: 14px; }

        .trainers { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }
        .trainer { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; }
        .trainer img, .trainer .ph { width: 100%; aspect-ratio: 1; object-fit: cover; background: color-mix(in srgb, var(--brand) 18%, #fff); display: grid; place-items: center; font-family: Sora; font-size: 3rem; font-weight: 800; color: var(--brand); }
        .trainer div.body { padding: 18px; }
        .trainer h3 { font-size: 1.05rem; } .trainer .role { color: var(--brand); font-size: .85rem; font-weight: 600; }
        .trainer p { color: var(--muted); font-size: .9rem; margin: 8px 0 0; }

        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
        .gallery a { display: block; border-radius: 14px; overflow: hidden; aspect-ratio: 4/3; }
        .gallery img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
        .gallery a:hover img { transform: scale(1.06); }

        .visit { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 860px) { .visit { grid-template-columns: 1fr; } }
        .card { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 28px; }
        .hours { width: 100%; border-collapse: collapse; }
        .hours td { padding: 8px 0; border-bottom: 1px solid var(--line); }
        .hours tr.today td { font-weight: 700; color: var(--brand); }
        .hours td:last-child { text-align: right; }
        .contact-list { list-style: none; padding: 0; margin: 0 0 20px; display: grid; gap: 12px; }
        .contact-list li { display: flex; gap: 12px; } .contact-list .ic { color: var(--brand); width: 22px; flex: none; }

        .trial { display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; align-items: start; }
        @media (max-width: 860px) { .trial { grid-template-columns: 1fr; } }
        .trial-perks { list-style: none; padding: 0; margin: 20px 0 0; display: grid; gap: 10px; }
        .trial-perks li::before { content: "✓"; color: var(--brand); font-weight: 800; margin-right: 10px; }
        form .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 560px) { form .grid2 { grid-template-columns: 1fr; } }
        label { display: block; font-weight: 600; font-size: .88rem; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 11px 13px; border: 1px solid #d5d7e0; border-radius: 10px; font: inherit; background: #fff; }
        input:focus, select:focus, textarea:focus { outline: 2px solid color-mix(in srgb, var(--brand) 40%, transparent); border-color: var(--brand); }
        .field { margin-bottom: 14px; }
        .error { color: #b91c1c; font-size: .84rem; margin-top: 4px; }
        .success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 14px; padding: 22px; }
        .hp { position: absolute; left: -9999px; }

        footer { background: #0d0e14; color: #b6b9c6; padding: 40px 0; font-size: .92rem; }
        footer .container { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        footer a { color: #fff; text-decoration: none; margin-right: 16px; }
        .float-wa { position: fixed; right: 18px; bottom: 18px; z-index: 40; width: 56px; height: 56px; border-radius: 50%; background: #25d366; color: #fff; display: grid; place-items: center; box-shadow: 0 10px 24px -6px rgba(0,0,0,.4); text-decoration: none; font-size: 1.6rem; }
    </style>
</head>
<body>
    <header class="nav">
        <div class="container">
            <a class="brand" href="#top">
                @if ($gym->logo_url)<img src="{{ $gym->logo_url }}" alt="{{ $gym->name }} logo">@else<span class="mark">{{ mb_substr($gym->name, 0, 1) }}</span>@endif
                {{ $gym->name }}
            </a>
            <nav class="nav-links" aria-label="Sections">
                @if ($about)<a href="#about">About</a>@endif
                @if ($plans->isNotEmpty())<a href="#plans">Plans</a>@endif
                @if ($trainers->isNotEmpty())<a href="#trainers">Trainers</a>@endif
                @if ($gallery->isNotEmpty())<a href="#gallery">Gallery</a>@endif
                <a href="#visit">Visit</a>
            </nav>
            <a class="btn btn-brand" href="#trial">Book free trial</a>
        </div>
    </header>

    <main id="top">
        <section class="hero" aria-label="{{ $gym->name }}">
            @if ($cover)<div class="hero-bg" style="background-image: url('{{ $cover }}')"></div>@endif
            <div class="container">
                <h1>{{ $tagline }}</h1>
                <p>{{ $gym->name }}{{ $place ? ' · '.$place : '' }}. Come in for a free trial session and see the difference for yourself.</p>
                <div class="hero-cta">
                    <a class="btn btn-brand" href="#trial">Book your free trial →</a>
                    <a class="btn btn-light" href="{{ $joinUrl }}">Join now</a>
                </div>
                <div class="chips">
                    @if ($today)
                        @if ($today['closed'] ?? false)
                            <span class="chip closed">Closed today</span>
                        @else
                            <span class="chip open">Open today {{ $time($today['opens'] ?? null) }} – {{ $time($today['closes'] ?? null) }}</span>
                        @endif
                    @endif
                    @if ($gym->phone)<a class="chip" href="tel:{{ $gym->phone }}" style="text-decoration:none">☎ {{ $gym->phone }}</a>@endif
                    @if ($place)<span class="chip">📍 {{ $place }}</span>@endif
                </div>
            </div>
        </section>

        @if ($about)
            <section id="about">
                <div class="container about">
                    <div>
                        <div class="eyebrow">About us</div>
                        <h2 style="font-size: clamp(1.8rem, 4vw, 2.6rem); margin: 10px 0 14px">Welcome to {{ $gym->name }}</h2>
                        <p>{{ $about }}</p>
                    </div>
                    @if ($gallery->isNotEmpty())<img src="{{ $gallery->first() }}" alt="Inside {{ $gym->name }}" loading="lazy">@endif
                </div>
            </section>
        @endif

        @if ($plans->isNotEmpty())
            <section id="plans" class="soft">
                <div class="container">
                    <div class="head">
                        <div class="eyebrow">Membership plans</div>
                        <h2>Pick the plan that fits you</h2>
                        <p>Not sure yet? Start with a free trial and we'll help you choose.</p>
                    </div>
                    <div class="plans">
                        @foreach ($plans as $plan)
                            <div class="plan">
                                <h3>{{ $plan->name }}</h3>
                                <div class="price">{{ Money::symbol($gym->currency).number_format((float) $plan->price, fmod((float) $plan->price, 1) ? 2 : 0) }}</div>
                                <div class="per">for {{ $plan->duration_label }}</div>
                                <p>
                                    {{ $plan->description ?: ($plan->max_persons > 1 ? 'For '.$plan->max_persons.' people.' : 'Full gym access.') }}
                                    @if ($gym->isGstRegistered() && $plan->gst_rate > 0)<br><small>+ GST</small>@endif
                                </p>
                                <a class="btn btn-outline" href="{{ $joinUrl }}">Join with this plan</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($trainers->isNotEmpty())
            <section id="trainers">
                <div class="container">
                    <div class="head">
                        <div class="eyebrow">Our team</div>
                        <h2>Train with people who care</h2>
                    </div>
                    <div class="trainers">
                        @foreach ($trainers as $trainer)
                            <article class="trainer">
                                @if ($trainer->photo_path)
                                    <img src="{{ Storage::disk('public')->url($trainer->photo_path) }}" alt="{{ $trainer->user->name }}" loading="lazy">
                                @else
                                    <div class="ph" aria-hidden="true">{{ mb_substr($trainer->user->name, 0, 1) }}</div>
                                @endif
                                <div class="body">
                                    <h3>{{ $trainer->user->name }}</h3>
                                    <div class="role">{{ $trainer->designation ?: $trainer->role->getLabel() }}</div>
                                    @if ($trainer->specialities)<p><strong>{{ $trainer->specialities }}</strong></p>@endif
                                    @if ($trainer->bio)<p>{{ $trainer->bio }}</p>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($gallery->isNotEmpty())
            <section id="gallery" class="{{ $trainers->isNotEmpty() ? 'soft' : '' }}">
                <div class="container">
                    <div class="head">
                        <div class="eyebrow">Gallery</div>
                        <h2>Take a look inside</h2>
                    </div>
                    <div class="gallery">
                        @foreach ($gallery as $i => $photo)
                            <a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="{{ $gym->name }} photo {{ $i + 1 }}" loading="lazy"></a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section id="visit">
            <div class="container">
                <div class="head">
                    <div class="eyebrow">Visit us</div>
                    <h2>Opening hours & location</h2>
                </div>
                <div class="visit">
                    <div class="card">
                        <h3 style="margin-bottom: 12px">Opening hours</h3>
                        @if ($hours->isNotEmpty())
                            <table class="hours">
                                @foreach ($hours as $row)
                                    <tr class="{{ $row['day'] === $todayName ? 'today' : '' }}">
                                        <td>{{ ucfirst($row['day']) }}</td>
                                        <td>{{ ($row['closed'] ?? false) ? 'Closed' : $time($row['opens'] ?? null).' – '.$time($row['closes'] ?? null) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <p style="color: var(--muted)">Call us for today's timings.</p>
                        @endif
                    </div>
                    <div class="card">
                        <h3 style="margin-bottom: 12px">Find us</h3>
                        <ul class="contact-list">
                            @if ($gym->address || $place)<li><span class="ic">📍</span><span>{{ collect([$gym->address, $place, $gym->pincode])->filter()->implode(', ') }}</span></li>@endif
                            @if ($gym->phone)<li><span class="ic">☎</span><a href="tel:{{ $gym->phone }}">{{ $gym->phone }}</a></li>@endif
                            @if ($gym->email)<li><span class="ic">✉</span><a href="mailto:{{ $gym->email }}">{{ $gym->email }}</a></li>@endif
                        </ul>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap">
                            @if ($gym->websiteSetting('map_url'))<a class="btn btn-outline" href="{{ $gym->websiteSetting('map_url') }}" target="_blank" rel="noopener">Get directions</a>@endif
                            @if ($whatsapp)<a class="btn btn-outline" href="https://wa.me/{{ \App\Services\Messaging\PhoneNumber::international($whatsapp) }}" target="_blank" rel="noopener">WhatsApp us</a>@endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="trial" class="soft">
            <div class="container trial">
                <div>
                    <div class="eyebrow">Free trial</div>
                    <h2 style="font-size: clamp(1.8rem, 4vw, 2.6rem); margin: 10px 0 10px">Your first session is on us</h2>
                    <p style="color: var(--muted)">Pick a day and time. We'll confirm on WhatsApp or a call.</p>
                    <ul class="trial-perks">
                        <li>Full access to the gym floor</li>
                        <li>A trainer to show you around</li>
                        <li>No payment, no obligation</li>
                    </ul>
                </div>
                <div class="card">
                    @if (session('trial_booked'))
                        <div class="success" role="status">
                            <h3 style="margin-bottom: 6px">You're booked in! 🎉</h3>
                            We'll contact you shortly to confirm your free trial at {{ $gym->name }}.
                        </div>
                    @else
                        <form method="POST" action="{{ route('website.trial', $gym) }}">
                            @csrf
                            <div class="hp" aria-hidden="true"><label for="website_url">Leave empty</label><input id="website_url" name="website_url" tabindex="-1" autocomplete="off"></div>
                            <div class="grid2">
                                <div class="field">
                                    <label for="name">Your name</label>
                                    <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
                                    @error('name')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field">
                                    <label for="phone">Mobile number</label>
                                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel">
                                    @error('phone')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field">
                                    <label for="trial_date">Preferred day</label>
                                    <input id="trial_date" name="trial_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('trial_date', today()->addDay()->toDateString()) }}" required>
                                    @error('trial_date')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field">
                                    <label for="trial_time">Preferred time</label>
                                    <select id="trial_time" name="trial_time" required>
                                        @foreach (['Morning', 'Afternoon', 'Evening'] as $slot)
                                            <option @selected(old('trial_time') === $slot)>{{ $slot }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="email">Email (optional)</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                                    @error('email')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field">
                                    <label for="interest">I'm interested in</label>
                                    <select id="interest" name="interest">
                                        <option value="">Choose…</option>
                                        @foreach (['Weight loss', 'Muscle gain', 'General fitness', 'Personal training', 'Strength training'] as $goal)
                                            <option @selected(old('interest') === $goal)>{{ $goal }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label for="message">Anything we should know? (optional)</label>
                                <textarea id="message" name="message" rows="2">{{ old('message') }}</textarea>
                            </div>
                            <button class="btn btn-brand" type="submit" style="width: 100%">Book my free trial</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div>
                <strong style="color: #fff">{{ $gym->name }}</strong> · © {{ now()->year }}
                <div style="margin-top: 6px">
                    @foreach ($socials as $key => $label)<a href="{{ $gym->websiteSetting($key) }}" target="_blank" rel="noopener">{{ $label }}</a>@endforeach
                    <a href="{{ route('filament.member.auth.login') }}">Member login</a>
                </div>
            </div>
            <div>Powered by <a href="{{ route('home') }}" style="margin: 0">{{ config('app.name') }}</a></div>
        </div>
    </footer>

    @if ($whatsapp)
        <a class="float-wa" href="https://wa.me/{{ \App\Services\Messaging\PhoneNumber::international($whatsapp) }}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">✆</a>
    @endif
</body>
</html>
