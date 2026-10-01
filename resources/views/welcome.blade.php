<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'DEZA Voice') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }
        body {
            position: relative;
            min-height: 100vh;
            background-image: url('{{ asset('welcome_bg.jpg') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.35) 55%, rgba(0,0,0,0.9) 100%);
            pointer-events: none;
        }
        .content {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            text-align: center;
            padding: 24px 32px 64px;
        }
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .logo svg { width: 34px; height: 34px; flex-shrink: 0; }
        .logo span {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #fff;
        }
        .logo .accent { color: #FF6A1A; }
        p {
            font-size: 14px;
            color: rgba(255,255,255,0.75);
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background-color: #FF6A1A;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            padding: 14px 24px;
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }
        .btn:hover { background-color: #e85f14; }
        .btn svg { width: 18px; height: 18px; }
        footer {
            margin-top: 24px;
            font-size: 12px;
            color: rgba(255,255,255,0.45);
        }
    </style>
</head>
<body>
    <div class="overlay"></div>

    <div class="content">
        <div class="logo">
            <svg viewBox="0 0 64 64" aria-hidden="true">
                <rect width="64" height="64" rx="14" fill="#0D1B2A"></rect>
                <rect width="64" height="64" rx="14" fill="none" stroke="#3A4A5F" stroke-opacity="0.6"></rect>
                <rect x="14" y="26" width="5" height="12" rx="2.5" fill="#35C2D6"></rect>
                <rect x="23" y="18" width="5" height="28" rx="2.5" fill="#35C2D6"></rect>
                <rect x="32" y="22" width="5" height="20" rx="2.5" fill="#FF6A00"></rect>
                <rect x="41" y="15" width="5" height="34" rx="2.5" fill="#35C2D6"></rect>
                <rect x="50" y="27" width="5" height="10" rx="2.5" fill="#2DBB7F"></rect>
            </svg>
            <span>DEZA <span class="accent">Voice</span></span>
        </div>

        <p>Access reserved for DEZA Voice sales team members. Sign in with your credentials to register clients and track your sales.</p>

        <a href="{{ url('/login') }}" class="btn">
            Sign in
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
        </a>

        <footer>DEZA Voice &middot; by DEZA Labs</footer>
    </div>
</body>
</html>