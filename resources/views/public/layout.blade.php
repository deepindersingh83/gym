<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <style>
        :root { --brand: {{ $brand ?? '#f59e0b' }}; --ink: #111827; --muted: #6b7280; --line: #e5e7eb; --bg: #f9fafb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.5; }
        a { color: inherit; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; margin-bottom: 20px; }
        .hero { display: flex; gap: 16px; align-items: center; }
        .hero img { width: 72px; height: 72px; object-fit: contain; border-radius: 12px; }
        h1 { margin: 0; font-size: 1.8rem; }
        h2 { margin: 0 0 12px; font-size: 1.2rem; }
        .muted { color: var(--muted); }
        .grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .plan { border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .plan strong { font-size: 1.3rem; }
        label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; font: inherit; background: #fff; }
        .check { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; }
        .check input { width: auto; margin-top: 4px; }
        .btn { display: inline-block; background: var(--brand); color: #fff; border: 0; border-radius: 10px; padding: 12px 22px; font-weight: 700; font-size: 1rem; cursor: pointer; text-decoration: none; }
        .btn.secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .error { color: #b91c1c; font-size: .85rem; margin-top: 4px; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
        table.hours td { padding: 2px 16px 2px 0; }
        .brandbar { height: 6px; background: var(--brand); }
    </style>
</head>
<body>
    <div class="brandbar"></div>
    <div class="wrap">
        @yield('content')
    </div>
</body>
</html>
