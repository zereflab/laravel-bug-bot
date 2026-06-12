<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bug Reports Setup Required — LaravelBugBot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { align-items: center; background: #0b0d14; color: #e7e9f0; display: flex; font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; justify-content: center; margin: 0; min-height: 100vh; color-scheme: dark; }
        .card { background: #161a28; border: 1px solid #232839; border-radius: 18px; max-width: 660px; padding: 30px 34px; }
        h1 { font-size: 24px; letter-spacing: -0.01em; margin: 0 0 12px; }
        p { color: #9aa1b5; line-height: 1.6; }
        code { background: #0b0d14; border: 1px solid #232839; border-radius: 10px; color: #e7e9f0; display: block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 14px; margin-top: 16px; padding: 14px 16px; }
        .small { color: #9aa1b5; font-size: 13px; margin-top: 18px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Bug Reports needs its database tables</h1>
        <p>Run the package migrations before opening the dashboard.</p>
        <code>php artisan migrate</code>
        <div class="small">{{ $message }}</div>
    </main>
</body>
</html>
