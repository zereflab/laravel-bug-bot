<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bug Reports Setup Required</title>
    <style>
        body { align-items: center; background: #f6f7fb; color: #111827; display: flex; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; justify-content: center; margin: 0; min-height: 100vh; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); max-width: 680px; padding: 28px; }
        h1 { margin: 0 0 12px; }
        p { color: #4b5563; line-height: 1.6; }
        code { background: #111827; border-radius: 10px; color: #fff; display: block; margin-top: 16px; padding: 14px; }
        .small { color: #6b7280; font-size: 13px; margin-top: 18px; }
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
