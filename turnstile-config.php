<?php
/**
 * Публичный site key для Cloudflare Turnstile (секрет остаётся в config.php).
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$key = trim((string)($cfg['turnstile_site_key'] ?? ''));
$enabled = array_key_exists('turnstile_enabled', $cfg) ? (bool)$cfg['turnstile_enabled'] : ($key !== '');

echo 'window.TURNSTILE_SITE_KEY=' . json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
echo 'window.TURNSTILE_ENABLED=' . ($enabled ? 'true' : 'false') . ';';
