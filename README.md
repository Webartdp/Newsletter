# DnepritNewsletter

Компонент email-розсилок для MODX Revolution 2.8.1.

## Реалізовано

- xPDO-моделі підписників, кампаній, персональної черги та журналу;
- CMP на ExtJS для підписників, кампаній, черги, журналу та налаштувань;
- CRUD підписників, статуси, масові операції та імпорт CSV/TXT;
- HTML-редактор кампаній, текстова версія й персональні плейсхолдери;
- формування персональної черги з негайним або запланованим стартом;
- браузерна пакетна відправка з продовженням збереженої черги;
- optional CLI Cron worker із блокуванням, лімітами й повторними спробами;
- повторна перевірка статусу підписника перед кожним листом;
- моніторинг черги, SMTP-помилок, ручних повторів і статистики кампанії;
- публічна AJAX-форма підписки без залежності від jQuery;
- одноразові file-backed form tokens, honeypot, мінімальний час заповнення, Origin validation та rate limiting;
- безпечна сторінка відписки з окремим POST-підтвердженням;
- український і російський інтерфейс.

## Публічна форма підписки

Викликайте сніпет **некешованим**, оскільки кожен рендер форми створює одноразовий токен:

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
tpl                 власний чанк форми
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
- передавати `email`;
- передавати `name`, якщо використовується ім’я;
- передавати checkbox `consent=1`, якщо `requireConsent=1`;
- містити порожнє honeypot-поле `website`;
- містити елемент з `data-dneprit-newsletter-message` для відповіді AJAX.

Якщо у власному дизайні checkbox згоди відсутній, вимогу потрібно вимкнути саме для цього виклику:

```modx
[[!DnepritNewsletterSubscribe?
    &tpl=`MyNewsletterForm`
    &source=`footer`
    &requireConsent=`0`
]]
```

Публічні токени зберігаються як короткоживучі одноразові JSON-файли у:

```text
core/cache/dnepritnewsletter/form-tokens/
```

Після успішної підписки використаний token видаляється, а endpoint повертає новий `form_token`. JavaScript оновлює приховане поле без перезавантаження сторінки.

`subscribe.js` обробляє submit у capture phase, одразу фіксує token і формує `FormData` явно. Це не дозволяє стороннім submit-обробникам сайту очистити `form_token` до AJAX-запиту.

Успішна публічна відповідь навмисно однакова для нової, вже активної або заблокованої адреси. Це не дозволяє використовувати форму для перевірки, чи існує email у базі. Відписаний підписник може бути повторно активований, якщо дозволено відповідним налаштуванням.

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

3. Вкажіть ID ресурсу в налаштуваннях DnepritNewsletter або системному налаштуванні:

```text
dnepritnewsletter.unsubscribe_resource_id
```

Плейсхолдер `[[+unsubscribe_url]]` у листі автоматично веде на цей ресурс із параметром `newsletter_token`.

GET-запит лише показує сторінку підтвердження. Статус підписника змінюється тільки після POST-підтвердження, тому поштові сканери посилань не можуть випадково відписати користувача простим відкриттям URL.

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

Підтримуються `.csv` і `.txt`. Автоматично визначаються кома, крапка з комою, табуляція або вертикальна риска. TXT без роздільників обробляється як один email у рядку.

Тимчасові файли зберігаються в:

```text
core/cache/dnepritnewsletter/imports/
```

і автоматично видаляються.

## Плейсхолдери листа

```text
[[+name]]
[[+email]]
[[+unsubscribe_url]]
[[+site_name]]
```

Під час формування черги значення фіксуються для конкретного одержувача. Персональні значення в HTML екрануються, а тема очищується від переносів рядка.

## Налаштування пошти

Відправлення використовує стандартний mail transport MODX. SMTP налаштовується системними параметрами MODX, зокрема:

```text
mail_use_smtp
mail_smtp_hosts
mail_smtp_port
mail_smtp_user
mail_smtp_pass
mail_smtp_prefix
```

У вкладці **Налаштування** DnepritNewsletter доступні sender defaults, batch size, rate limits, retry settings, import/public-form settings і read-only SMTP status.

## Відправка кампаній

Основний ручний сценарій працює прямо в CMP:

1. створити кампанію;
2. сформувати чергу;
3. запустити відправку одразу або натиснути **Запустити розсилку**;
4. тримати вкладку менеджера відкритою під час browser batches;
5. якщо вкладка була закрита, відкрити компонент знову й продовжити залишок черги.

Черга зберігається на сервері, тому закриття браузера не видаляє невідправлені записи.

## Optional Cron

Для unattended delivery можна запускати worker щохвилини:

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

Основні параметри доставки:

```text
dnepritnewsletter.batch_size
dnepritnewsletter.limit_per_minute
dnepritnewsletter.limit_per_hour
dnepritnewsletter.max_attempts
dnepritnewsletter.retry_delay
dnepritnewsletter.lock_ttl
```

## Моніторинг у CMP

Вкладка **Черга** показує статус, спроби, час наступної спроби, worker і SMTP-помилку. Записи `failed` можна вручну повернути в чергу. Доступні також видалення вибраних записів черги та перерахунок статистики кампанії.

Вкладка **Журнал** показує системні, поштові та публічні події, включно з `public_subscribe_created`, `public_subscribe_reactivated` і `public_unsubscribe`.

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
- налаштований поштовий транспорт MODX;
- writable `core/cache/` для rate-limit та form-token файлів.

## Збірка пакета

Розмістіть репозиторій у корені тестової установки MODX або передайте `MODX_BASE_PATH`, потім запустіть:

```bash
php _build/build.transport.php
```

Transport package буде створено в `_dist/`.

Поточний prerelease:

```text
dnepritnewsletter-0.1.0-beta6.transport.zip
```

## Ліцензія

MIT.
