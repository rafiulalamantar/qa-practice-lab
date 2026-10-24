<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>TicketTrail API Docs</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>body { margin: 0; background: #fafafa; } .tt-docs-bar { padding: 10px 20px; background: #312e81; color: #fff; font: 14px system-ui, sans-serif; } .tt-docs-bar a { color: #c7d2fe; }</style>
</head>
<body>
    <div class="tt-docs-bar">TicketTrail API &middot; <a href="{{ url('/tickets') }}">open the app</a> &middot; <a href="{{ url('/api/tickets/openapi.json') }}">openapi.json</a> (import into Postman)</div>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({ url: @json(url('/api/tickets/openapi.json')), dom_id: '#swagger-ui', persistAuthorization: true, tryItOutEnabled: true });
    </script>
</body>
</html>
