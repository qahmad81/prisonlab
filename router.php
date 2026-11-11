<?php
require_once __DIR__ . '/lib/i18n.php';
// Explicit public allowlist also protects .env and legacy logs under PHP's development server.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (in_array($path, ['/', '/index.php', '/public.php', '/privacy.php', '/api.php', '/mcp.php', '/favicon.svg', '/favicon.ico'], true)) {
    if ($path === '/') { require __DIR__ . '/index.php'; return true; }
    return false;
}
if ($route = labParsePagePath(labRequestPath())) {
    $entries = ['home' => 'index.php', 'public' => 'public.php', 'privacy' => 'privacy.php'];
    require __DIR__ . '/' . $entries[$route['page']];
    return true;
}
if (preg_match('~^/assets/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\.(?:js|css|jpg|jpeg|png|webp|svg)$~D', $path)
    && !str_contains($path, '..') && is_file(__DIR__ . $path)) return false;
http_response_code(404); echo labT('router.router.1'); return true;
