<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; vertical-align: top; }
        .items th { background: #f3f4f6; border-bottom: 1px solid #d1d5db; font-size: 10px; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .total td { font-weight: bold; font-size: 12px; border-top: 2px solid #1f2937; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .paid { background: #dcfce7; color: #166534; }
        .due { background: #fee2e2; color: #991b1b; }
        .header td { padding: 0; }
        .brand { border-bottom: 3px solid {{ $gym->primary_color ?? '#f59e0b' }}; padding-bottom: 10px; margin-bottom: 14px; }
        .footer { margin-top: 24px; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="brand">
        <table class="header">
            <tr>
                <td style="width: 70px">
                    @if ($gym->logo_path && is_file($logo = \Illuminate\Support\Facades\Storage::disk('public')->path($gym->logo_path)))
                        <img src="data:{{ mime_content_type($logo) }};base64,{{ base64_encode(file_get_contents($logo)) }}" style="max-width: 60px; max-height: 60px">
                    @endif
                </td>
                <td>
                    <h1>{{ $gym->name }}</h1>
                    <div class="muted">
                        {{ collect([$gym->address, $gym->city, $gym->state, $gym->pincode])->filter()->implode(', ') }}<br>
                        {{ collect([$gym->phone, $gym->email])->filter()->implode(' · ') }}
                        @if ($gym->gstin)<br>GSTIN: {{ $gym->gstin }}@endif
                    </div>
                </td>
                <td class="num" style="width: 200px">
                    @yield('heading')
                </td>
            </tr>
        </table>
    </div>

    @yield('content')

    <div class="footer">This is a computer-generated document and does not need a signature.</div>
</body>
</html>
