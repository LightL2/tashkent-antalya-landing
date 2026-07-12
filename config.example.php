<?php
/**
 * Пример конфигурации. Скопируйте в config.php и впишите свои значения:
 *   cp config.example.php config.php
 * Файл config.php в git НЕ попадает (секреты).
 */
return [
    // Токен Telegram-бота от @BotFather
    'telegram_token'   => 'PASTE_YOUR_BOT_TOKEN_HERE',
    // ID чата/группы для заявок. Узнать chat_id: напишите боту, затем откройте
    // https://api.telegram.org/bot<TOKEN>/getUpdates и возьмите chat.id
    'telegram_chat_id' => 'PASTE_YOUR_CHAT_ID_HERE',

    // Cloudflare Turnstile — https://dash.cloudflare.com → Turnstile → Add widget (Managed)
    // В Hostname Management добавьте ВСЕ домены сайта (напр. tashkent.asialuxe.uz)
    'turnstile_enabled'    => true,
    'turnstile_site_key'   => '',
    'turnstile_secret_key' => '',
];