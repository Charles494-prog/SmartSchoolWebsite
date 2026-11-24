<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · SmartSchool</title>
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f8fafc; color: #0f172a; padding: 24px; }
        @media (prefers-color-scheme: dark) { body { background: #0b0f19; color: #e5e7eb; } .muted { color: #94a3b8 !important; } a.btn { background: #e5e7eb !important; color: #0b0f19 !important; } }
        main { max-width: 440px; text-align: center; }
        .code { font-size: 14px; font-weight: 600; color: #4f46e5; letter-spacing: .08em; }
        h1 { font-size: 28px; margin: 8px 0; letter-spacing: -.02em; }
        .muted { color: #64748b; margin: 0 0 28px; }
        a.btn { display: inline-flex; min-height: 44px; align-items: center; padding: 0 20px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; }
        .rid { margin-top: 24px; font-size: 12px; color: #94a3b8; font-family: ui-monospace, monospace; }
    </style>
</head>
<body>
<main>
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p class="muted">@yield('message')</p>
    <a class="btn" href="{{ url('/') }}">Go to home</a>
    @if(request()->attributes->get('request_id'))<div class="rid">Reference: {{ request()->attributes->get('request_id') }}</div>@endif
</main>
</body>
</html>
