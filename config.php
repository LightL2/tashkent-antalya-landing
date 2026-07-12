<?php
/**
 * Конфигурация сайта (Telegram, Turnstile).
 */return [
    // Токен Telegram-бота (BotFather)
    'telegram_token'   => '8943099263:AAFq9dPjVmYffzytaWaAyh65wm_faTuNZp8',
    // ID чата/группы, куда слать заявки (см. README, раздел "Telegram")
    'telegram_chat_id' => '5887158228',

    // Cloudflare Turnstile — https://dash.cloudflare.com → Turnstile
    'turnstile_enabled'    => true,
    'turnstile_site_key'   => '0x4AAAAAAADtNGwEOWGIXVn19',
    'turnstile_secret_key' => '0x4AAAAAAADtNG845jlcWXxaFDlPyajbHLbI',
];