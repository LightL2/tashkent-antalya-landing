# Asialuxe — Лендинг «Прямые рейсы Ташкент → Анталья»

Адаптивный двуязычный (RU / UZ) лендинг по брендбуку Asialuxe Travel.
Стек: **HTML + CSS + JS + PHP**. Никакого Node, сборки и внешних сервисов —
просто загрузите папку на обычный хостинг с PHP.

Заявки уходят в **Telegram-бот** и пишутся в файл **`leads.csv`**.
Подключены **GA4** `G-JF536BCQJP`, **Google Ads** `AW-18224907931`, **Meta Pixel**, **Yandex Metrika** `109751746`.

## Структура
```
tashkent-antalya-landing/
├── index.html
├── lead.php
├── config.php
├── turnstile-config.php
├── assets/
│   ├── css/styles.css
│   ├── js/i18n.js
│   ├── js/app.js
│   └── img/
└── README.md
```

## Офис
- **Адрес:** г. Ташкент, проспект Амира Темура, 24
- **Телефон:** +998 (71) 201 11 11
- **Карта:** [Yandex Maps](https://yandex.uz/maps/10335/tashkent/?from=mapframe&ll=69.283593,41.298579&mode=routes&rtext=~41.298579,69.283593&rtt=auto&ruri=~ymapsbm1://org?oid=79190462171&z=14)

## Запуск
1. Загрузите папку на хостинг с PHP.
2. Настройте `config.php` (Telegram, Turnstile).
3. В Cloudflare Turnstile добавьте домен сайта (напр. `tashkent.asialuxe.uz`).

## Локальный предпросмотр
```
php -S 127.0.0.1:8000
```
