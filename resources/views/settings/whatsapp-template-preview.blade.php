<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $template->name }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f4f6fa; }
        .phone { max-width: 420px; margin: 0 auto; background: #e5ddd5; border-radius: 18px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.16); }
        .bubble { background: #dcf8c6; border-radius: 8px; padding: 12px 14px; white-space: pre-wrap; line-height: 1.45; }
        .toolbar { max-width: 420px; margin: 0 auto 15px; text-align: right; }
        .toolbar button { background: #25d366; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>
    <div class="phone">
        <h3>{{ $tenant->name }} — {{ $template->name }}</h3>
        <div class="bubble">{{ $template->body }}</div>
    </div>
</body>
</html>
