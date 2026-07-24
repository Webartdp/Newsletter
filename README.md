# DnepritNewsletter

Компонент email-розсилок для MODX Revolution 2.8.1.

## Реалізовано

- xPDO-моделі підписників, кампаній, персональної черги та журналу;
- CMP на ExtJS для підписників, кампаній, черги й журналу;
- CRUD підписників, статуси, масові операції та імпорт CSV/TXT;
- HTML-редактор кампаній, текстова версія й персональні плейсхолдери;
- формування персональної черги з негайним або запланованим стартом;
- пакетна відправка через CLI Cron із блокуванням, лімітами й повторними спробами;
- повторна перевірка статусу підписника перед кожним листом;
- моніторинг черги, SMTP-помилок, ручних повторів і статистики кампанії;
- публічна AJAX-форма підписки без залежності від jQuery;
- session-токен, honeypot, мінімальний час заповнення та rate limiting;
- безпечна сторінка відписки з окремим POST-підтвердженням;
- український і російський інтерфейс.

## Публічна форма підписки

Викликайте сніпет **некешованим**, оскільки кожна форма містить персональний session-токен:

```modx
[[!DnepritNewsletterSubscribe]]
```

Приклад із параметрами:

```modx
[[!DnepritNewsletterSubscribe?
    &source=`footer`
    &showName=`1`
    &requireName=`0`
    &requireConsent=`1`
    &buttonText=`Підписатися`
]]
```

Основні параметри:

```text
source              джерело підписки, за замовчуванням website
showName            показувати поле імені
requireName         зробити ім’я обов’язковим
requireConsent      вимагати checkbox згоди
emailLabel          підпис поля email
emailPlaceholder    placeholder email
nameLabel           підпис поля імені
namePlaceholder     placeholder імені
consentText         текст згоди
buttonText          текст кнопки
formClass           CSS-клас форми
formId              власний HTML id
loadCss             підключати стандартний web.css
loadJs              підключати AJAX-скрипт
tpl                  власний чанк форми
```

Для власного чанка передаються плейсхолдери:

```text
[[+form_id]]
[[+connector_url]]
[[+form_token]]
[[+source]]
[[+show_name]]
[[+require_name]]
[[+require_consent]]
[[+email_label]]
[[+email_placeholder]]
[[+name_label]]
[[+name_placeholder]]
[[+consent_text]]
[[+button_text]]
[[+form_class]]
```

Власна форма повинна:

- мати `action="[[+connector_url]]"`, `method="post"` і атрибут `data-dneprit-newsletter-form`;
- передавати приховане поле `form_token` зі значенням `[[+form_token]]`;
- передавати `email`, необов’язкове `name` і checkbox `consent=1`;
- містити порожнє honeypot-поле `website`;
- містити елемент з `data-dneprit-newsletter-message` для відповіді AJAX.

Успішна публічна відповідь навмисно однакова для нової, вже активної або заблокованої адреси. Це не дозволяє використовувати форму для перевірки, чи існує email у базі. Відписаний підписник може бути повторно активований; при цьому генерується новий токен відписки, а старі посилання перестають діяти.

Подія успішної підписки доступна у браузері:

```javascript
document.addEventListener('dnepritNewsletter:success', function (event) {
    console.log(event.detail);
});
```

## Захист публічної форми

```text
dnepritnewsletter.require_consent              1
dnepritnewsletter.reactivate_unsubscribed      1
dnepritnewsletter.subscribe_min_seconds        2
dnepritnewsletter.subscribe_token_ttl          7200
dnepritnewsletter.subscribe_ip_limit           10
dnepritnewsletter.subscribe_ip_window          600
dnepritnewsletter.subscribe_email_limit        3
dnepritnewsletter.subscribe_email_window       3600
```

Rate limiting зберігається у `core/cache/dnepritnewsletter/rate-limit/`. Значення ліміту `0` вимикає відповідне обмеження. Заголовок `Origin`, якщо браузер його надсилає, повинен відповідати домену сайту.

## Сторінка відписки

1. Створіть окремий ресурс MODX, наприклад `/unsubscribe/`.
2. Додайте в його вміст некешований виклик:

```modx
[[!DnepritNewsletterUnsubscribe]]
```

3. Вкажіть ID ресурсу в системному налаштуванні:

```text
dnepritnewsletter.unsubscribe_resource_id
```

Плейсхолдер `[[+unsubscribe_url]]` у листі автоматично веде на цей ресурс із параметром `newsletter_token`.

GET-запит лише показує сторінку підтвердження. Статус підписника змінюється тільки після POST із session-токеном. Завдяки цьому антивірусні та поштові сканери посилань не можуть випадково відписати користувача простим відкриттям URL.

Параметри сніпета відписки:

```text
tokenParam     назва URL-параметра, за замовчуванням newsletter_token
buttonText     текст кнопки підтвердження
tplConfirm     чанк підтвердження
tplSuccess     чанк успішної відповіді
tplError       чанк помилки
loadCss        підключати стандартний web.css
```

У чанки передаються `message`, `email`, `form_token`, `newsletter_token`, `token_param` і `button_text` залежно від стану.

## Імпорт підписників

Підтримуються `.csv` і `.txt` до 10 МБ за замовчуванням. Автоматично визначаються кома, крапка з комою, табуляція або вертикальна риска. TXT без роздільників обробляється як один email у рядку.

```text
dnepritnewsletter.import_max_size
```

Тимчасові файли зберігаються в `core/cache/dnepritnewsletter/imports/` і автоматично видаляються.

## Плейсхолдери листа

```text
[[+name]]
[[+email]]
[[+unsubscribe_url]]
[[+site_name]]
```

Під час формування черги значення фіксуються для конкретного одержувача. Персональні значення в HTML екрануються, а тема очищується від переносів рядка.

## Налаштування пошти

Відправлення використовує стандартний `modPHPMailer`. SMTP налаштовується системними параметрами MODX, зокрема `mail_use_smtp`, `mail_smtp_hosts`, `mail_smtp_port`, `mail_smtp_user`, `mail_smtp_pass` і `mail_smtp_prefix`.

## Cron

Рекомендований запуск щохвилини:

```cron
* * * * * /usr/bin/php /path/to/site/core/components/dnepritnewsletter/cron/send.php >> /path/to/site/core/cache/logs/dnepritnewsletter-cron.log 2>&1
```

Для нестандартного розташування MODX:

```cron
* * * * * MODX_BASE_PATH=/path/to/site /usr/bin/php /custom/core/components/dnepritnewsletter/cron/send.php
```

Разовий запуск із власною пачкою:

```bash
php core/components/dnepritnewsletter/cron/send.php --limit=20
```

Параметри відправника:

```text
dnepritnewsletter.batch_size         50
dnepritnewsletter.limit_per_minute   50
dnepritnewsletter.limit_per_hour     500
dnepritnewsletter.max_attempts       3
dnepritnewsletter.retry_delay        300
dnepritnewsletter.lock_ttl           3600
```

Worker використовує файлове блокування та статус `processing`. Повторні спроби мають експоненційну затримку. Застарілі блокування повертаються до черги після `lock_ttl`.

## Моніторинг у CMP

Вкладка **«Черга»** показує статус, спроби, час наступної спроби, worker і повну SMTP-помилку. Записи `failed` можна вручну повернути в чергу.

Вкладка **«Журнал»** показує системні, поштові та публічні події, включно з `public_subscribe_created`, `public_subscribe_reactivated` і `public_unsubscribe`.

Додаткові дозволи MODX:

```text
newsletter_queue_view
newsletter_queue_manage
newsletter_logs_view
newsletter_campaigns_view
newsletter_campaigns_manage
```

## Гарантія доставки

Черга реалізує **at least once**. Якщо SMTP уже прийняв лист, але база стала недоступною до збереження статусу `sent`, після завершення блокування можливе повторне надсилання. Повністю виключити такий випадок можна лише через провайдера з ідемпотентним API.

## Вимоги

- MODX Revolution 2.8.1;
- PHP 7.4+;
- MySQL або MariaDB;
- ExtJS 3.4 / MODExt;
- увімкнені PHP-сесії;
- налаштований поштовий транспорт MODX.

## Збірка пакета

Розмістіть репозиторій у корені тестової установки MODX або передайте `MODX_BASE_PATH`, потім запустіть:

```bash
php _build/build.transport.php
```

Transport package буде створено у `core/packages/`.

## План розробки

1. ~~CRUD підписників.~~
2. ~~Імпорт CSV/TXT.~~
3. ~~CRUD кампаній і редактор листа.~~
4. ~~Формування черги.~~
5. ~~Пакетна відправка через Cron.~~
6. ~~Журнал, ручні повтори та статистика.~~
7. ~~AJAX-підписка й захищена сторінка відписки.~~
8. Повна перевірка на MODX 2.8.1, документація й релізний transport package.

## Ліцензія

MIT.
