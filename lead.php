<?php
/**
 * Asialuxe — приём заявок (Tashkent → Antalya)
 * Чистый PHP. Работает на любом обычном хостинге с PHP.
 * Лежит рядом с index.html → форма постит на этот же файл (без CORS и внешних сервисов).
 *
 * Что делает:
 *   1. Принимает POST-заявку (JSON).
 *   2. Защита от ботов (honeypot, тайминг, валидация, антидубль, rate-limit).
 *   3. Шлёт уведомление в Telegram-бот.
 *   4. Дописывает строку в leads.csv (открывается в Excel / Google Sheets).
 *
 * Настройка: впишите CHAT_ID ниже (как узнать — см. README, раздел "Telegram").
 */

// ============== НАСТРОЙКИ ==============
// Секреты (токен, chat_id) вынесены в config.php — он НЕ попадает в git.
// Скопируйте config.example.php → config.php и впишите свои значения.
$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];

define('TELEGRAM_TOKEN',   $cfg['telegram_token']   ?? '');
define('TELEGRAM_CHAT_ID', $cfg['telegram_chat_id'] ?? '');
$turnstileEnabled = array_key_exists('turnstile_enabled', $cfg)
    ? (bool)$cfg['turnstile_enabled']
    : trim((string)($cfg['turnstile_secret_key'] ?? '')) !== '';
define('TURNSTILE_SECRET', $turnstileEnabled ? trim((string)($cfg['turnstile_secret_key'] ?? '')) : '');
const CSV_FILE       = __DIR__ . '/leads.csv';
const MIN_FILL_MS    = 2500;            // антибот: быстрее этого = бот
const RATE_LIMIT_SEC = 45;              // не чаще 1 заявки с IP за это время
// ======================================

header('Content-Type: application/json; charset=utf-8');

// Полифилы на случай, если на хостинге отключён mbstring
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return strlen((string)$s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null, $enc = null) {
        return $len === null ? substr((string)$s, $start) : substr((string)$s, $start, $len);
    }
}

function out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => true, 'service' => 'asialuxe-lead']);
}

// --- читаем тело ---
$raw = file_get_contents('php://input');
$d = json_decode($raw, true);
if (!is_array($d)) { $d = $_POST; }

// --- анти-бот: honeypot ---
if (!empty($d['hp_bot_x']) || !empty($d['hp_bot_y']) || !empty($d['website']) || !empty($d['company'])) {
    out(['ok' => true]);
}

// --- анти-бот: Cloudflare Turnstile ---
if (TURNSTILE_SECRET !== '') {
    $tsToken = trim((string)($d['turnstile'] ?? ''));
    if ($tsToken === '' || !verify_turnstile($tsToken, TURNSTILE_SECRET)) {
        out(['ok' => false, 'error' => 'captcha'], 422);
    }
}

// --- анти-бот: тайминг ---
if (isset($d['elapsed']) && (int)$d['elapsed'] < MIN_FILL_MS) {
    out(['ok' => true]);
}

// --- валидация ---
$name    = trim((string)($d['name'] ?? ''));
$phone   = trim((string)($d['phone'] ?? ''));
$comment = trim((string)($d['comment'] ?? ''));
$phoneNorm = normalize_uz_phone($phone);

if (!is_valid_name($name) || $phoneNorm === null || !is_valid_comment($comment)) {
    out(['ok' => true]); // тихо отбрасываем мусорные заявки
}

$phone = $phoneNorm;

// --- анти-бот: rate-limit по IP (через temp-файл) ---
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rlFile = sys_get_temp_dir() . '/al_rl_' . md5($ip);
if (is_file($rlFile) && (time() - filemtime($rlFile)) < RATE_LIMIT_SEC) {
    out(['ok' => true, 'dup' => true]); // не спамим, но клиенту ок
}
@touch($rlFile);

// --- собираем лид ---
$clean = function ($k, $max = 200) use ($d) {
    return mb_substr(trim((string)($d[$k] ?? '')), 0, $max);
};
$lead = [
    'date'        => date('Y-m-d H:i:s'),
    'name'        => mb_substr($name, 0, 120),
    'phone'       => mb_substr($phone, 0, 40),
    'contact'     => $clean('contact', 40),
    'adults'      => $clean('adults', 4),
    'children'    => $clean('children', 4),
    'flightClass' => $clean('flightClass', 30),
    'transfer'    => $clean('transfer', 30),
    'comment'     => $clean('comment', 1000),
    'lang'        => $clean('lang', 5),
    'page'        => $clean('page', 60),
    'url'         => $clean('url', 300),
    'ref'         => $clean('ref', 300),
    'utm_source'  => $clean('utm_source', 80),
    'utm_medium'  => $clean('utm_medium', 80),
    'utm_campaign'=> $clean('utm_campaign', 120),
];

// --- 1) пишем в CSV ---
save_csv($lead);
// --- 2) шлём в Telegram ---
send_telegram($lead);

out(['ok' => true]);


// ===================== функции =====================

function is_valid_name(string $name): bool {
    if (mb_strlen($name) < 3 || mb_strlen($name) > 80) { return false; }
    if (!preg_match('/\p{L}/u', $name)) { return false; }
    if (preg_match('/\d/u', $name)) { return false; }
    if (preg_match('/[#*@$%^&_=+\[\]{}|\\<>~`]/u', $name)) { return false; }
    if (preg_match('/(.)\1{4,}/u', $name)) { return false; }
    return preg_match_all('/\p{L}/u', $name) >= 2;
}

function normalize_uz_phone(string $phone): ?string {
    $digits = preg_replace('/\D/', '', $phone);
    if ($digits === '' || strlen($digits) > 12) { return null; }
    if (strlen($digits) === 9) { $digits = '998' . $digits; }
    if (strlen($digits) !== 12 || substr($digits, 0, 3) !== '998') { return null; }
    $op = substr($digits, 3, 2);
    $ops = ['90','91','93','94','95','97','98','99','33','50','88','77','20','71'];
    if (!in_array($op, $ops, true)) { return null; }
    return '+998 ' . substr($digits, 3, 2) . ' ' . substr($digits, 5, 3) . ' '
        . substr($digits, 8, 2) . ' ' . substr($digits, 10, 2);
}

function is_valid_comment(string $comment): bool {
    if ($comment === '') { return true; }
    if (mb_strlen($comment) > 500) { return false; }
    if (preg_match('/(.)\1{6,}/u', $comment)) { return false; }
    if (substr_count($comment, '.') > (int)(mb_strlen($comment) * 0.25)) { return false; }
    return true;
}

function verify_turnstile(string $token, string $secret): bool {
    $payload = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $raw = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
            ],
        ]));
    }

    if (!$raw) { return false; }
    $res = json_decode($raw, true);
    return is_array($res) && !empty($res['success']);
}

function save_csv(array $lead): void {
    $headers = ['Дата','Имя','Телефон','Связь','Взрослых','Детей','Класс','Трансфер','Комментарий','Язык','Страница','URL','Referrer','UTM Source','UTM Medium','UTM Campaign'];
    $isNew = !is_file(CSV_FILE);
    $fp = @fopen(CSV_FILE, 'a');
    if (!$fp) { return; }
    if (flock($fp, LOCK_EX)) {
        if ($isNew) {
            fwrite($fp, "\xEF\xBB\xBF"); // BOM — чтобы кириллица корректно открылась в Excel
            fputcsv($fp, $headers, ';');
        }
        fputcsv($fp, array_values($lead), ';');
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function send_telegram(array $lead): void {
    if (TELEGRAM_TOKEN === '' || TELEGRAM_CHAT_ID === '') { return; }
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $text  = "🆕 <b>Новая заявка — Ташкент → Анталья</b>\n\n";
    $text .= "👤 <b>Имя:</b> " . $e($lead['name']) . "\n";
    $text .= "📞 <b>Телефон:</b> " . $e($lead['phone']) . "\n";
    $text .= "💬 <b>Связь:</b> " . $e($lead['contact']) . "\n";
    $text .= "👥 <b>Взрослых:</b> " . $e($lead['adults']) . "  |  <b>Детей:</b> " . $e($lead['children']) . "\n";
    $text .= "✈️ <b>Класс:</b> " . $e($lead['flightClass']) . "\n";
    $text .= "🚐 <b>Трансфер:</b> " . $e($lead['transfer']) . "\n";
    if ($lead['comment'] !== '') { $text .= "📝 <b>Комментарий:</b> " . $e($lead['comment']) . "\n"; }
    $text .= "🌐 <b>Язык:</b> " . $e($lead['lang']) . "\n";
    if ($lead['utm_source'] !== '') {
        $text .= "📣 <b>UTM:</b> " . $e($lead['utm_source']);
        if ($lead['utm_medium'] !== '') { $text .= " / " . $e($lead['utm_medium']); }
        if ($lead['utm_campaign'] !== '') { $text .= " / " . $e($lead['utm_campaign']); }
        $text .= "\n";
    }
    if ($lead['url'] !== '') { $text .= "🔗 " . $e($lead['url']); }

    $payload = json_encode([
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_UNICODE);

    $url = 'https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/sendMessage';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } else {
        @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]));
    }
}
