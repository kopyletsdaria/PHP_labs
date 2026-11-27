# Практикум 8: Безпека PHP-застосунків: пошук і усунення типових вразливостей (Варіант 7)

**Виконала:** Копилець Дар'я, група ІО-44

## Структура проєкту

*   `api.php` - API трекера завдань: підготовлені запити, перевірка CSRF-токена, валідація, загальні повідомлення про помилки.
*   `security.php` - безпечна сесія (`HttpOnly`, `SameSite=Lax`) і функції CSRF-токена.
*   `db.php` - підключення до PostgreSQL (PDO).
*   `schema.sql` - схема БД: таблиця `tasks` та індекс `idx_tasks_priority` (можна виконувати повторно).
*   `profiler.php` - профілювання з практикуму №7.
*   `index.php` - сторінка з формою; токен виводиться через `htmlspecialchars()`.
*   `script.js` - клієнт: надсилає CSRF-токен з кожним POST-запитом.
*   `style.css` - файл стилів.

## Підсумок аудиту (варіант 7)

| Клас | Ціль варіанта | Payload | До | Після |
|------|---------------|---------|----|-------|
| SQL-ін'єкція | фільтр за `priority` | `' OR '1'='1` | усі запити вже підготовлені, вразливості немає | `400`: значення перевіряється за списком, а запит лишається підготовленим |
| XSS | поле `title` | `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>` | вразливості немає: вивід екранується в `escapeHTML()` | те саме, плюс `htmlspecialchars()` для токена і `nosniff` |
| CSRF | позначення виконаним (`markDone`) | `POST` без токена | `201` / `200`, дія виконувалась | `403` |
| Незахищена дія | зміна статусу через GET | `GET ...&action=complete&id=N` | `200` зі списком (дані не змінювались) | `405` + `Allow: POST` |


*   `POST` без токена: `curl -i -X POST "http://localhost:8001/api.php?resource=tasks" -d "title=Hack&due_date=2026-12-01&priority=Високий"` -> `201` (завдання створено).
*   `GET` з `action`: `curl -i "http://localhost:8001/api.php?resource=tasks&action=complete&id=3"` -> `200` зі списком замість `405`.
*   SQL-ін'єкція в фільтрі: у практикумі №7 фільтра за `priority` не було, а в практикумі №5 його обробляв `api_list.php` через підготовлений запит (`WHERE priority = :priority`), тому вразливості не було й тоді.

## Крок 1. SQL-ін'єкції

Усі SQL-запити в застосунку виконуються через `prepare()/execute()`; у текст запиту потрапляють лише константні фрагменти (`done = :done`, `priority = :priority`), значення передаються плейсхолдерами. Список усіх викликів до БД:

```bash
findstr /s /n /i "query( prepare( exec(" *.php
```

Фрагмент коду (`api.php`):

```php
if ($priority !== null) {
    $where[] = 'priority = :priority';
    $params['priority'] = $priority;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

Додатково значення фільтра перевіряється за списком дозволених:

```php
if ($priority !== null && (!is_string($priority) || !in_array($priority, PRIORITIES, true))) {
    fail(400, 'Поле priority має бути одним із: ' . implode(', ', PRIORITIES), 'priority');
}
```

**Перевірка payload у фільтрі `priority` (очікується `400`):**
```bash
curl -i -G "http://localhost:8000/api.php?resource=tasks" --data-urlencode "priority=' OR '1'='1"
```
<!-- скриншот -->

**Перевірка payload в `id` (очікується `400`):**
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&id=1%20OR%201%3D1"
```
<!-- скриншот -->

**Payload у полі `title` (підготовлений запит зберігає його як звичайний текст, таблиця не видаляється).** Спочатку отримайте токен (див. крок 3), потім:
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: ТОКЕН" --data-urlencode "title=x'); DROP TABLE tasks;--" -d "due_date=2026-12-01&priority=Високий"
```
<!-- скриншот -->

## Крок 2. XSS

Місця виводу даних користувача: таблиця завдань (`script.js`) і повідомлення про помилки. Дані вставляються через `escapeHTML()` (значення записується як текст і лише потім читається як HTML), повідомлення - через `innerText`:

```js
function escapeHTML(str) {
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}
```

У PHP значення, що виводиться в HTML (токен у `index.php`), екранується:

```php
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
```

API повертає `Content-Type: application/json` і заголовок `X-Content-Type-Options: nosniff`, тому браузер не інтерпретує відповідь як HTML.

**Перевірка:** на `index.php` у поле «Назва завдання» ввести `<script>alert(1)</script>`, потім `<img src=x onerror=alert(1)>`, натиснути «Додати» і оновити сторінку. Вікно `alert` не з'являється, а в таблиці payload відображається як текст.
<!-- скриншот -->

## Крок 3. CSRF

Токен створюється один раз на сесію (`security.php`) і виводиться в прихованому полі форми:

```php
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

`script.js` надсилає його в заголовку `X-CSRF-Token` з кожним POST (додавання і `markDone`). `api.php` перевіряє токен перед будь-якою обробкою POST:

```php
function requireCsrf(): void
{
    startSecureSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !csrfIsValid($token)) {
        fail(403, 'Недійсний або відсутній CSRF-токен. Оновіть сторінку');
    }
}
```

Cookie сесії має прапорці `HttpOnly` і `SameSite=Lax` (вкладка Application -> Cookies у DevTools).

**Отримати токен і сесію (токен скопіювати з відповіді):**
```bash
curl -i -c cookies.txt "http://localhost:8000/api.php?resource=csrf"
```
<!-- скриншот -->

**Без токена -> `403`:**
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks" -d "title=Hack&due_date=2026-12-01&priority=Високий"
```
<!-- скриншот -->

**Недійсний (старий) токен зі справжньою сесією -> `403`:**
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: stale123" -d "title=Hack&due_date=2026-12-01&priority=Високий"
```
<!-- скриншот -->

**Доменна дія без токена -> `403` (завдання не змінюється):**
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=3"
```
<!-- скриншот -->

**Правильний токен -> `201`:**
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: ТОКЕН" -d "title=Нове завдання&due_date=2026-12-01&priority=Високий"
```
<!-- скриншот -->

**Доменна дія з токеном -> `200`:**
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=3" -H "X-CSRF-Token: ТОКЕН"
```
<!-- скриншот -->

## Крок 4. Валідація на сервері

Обов'язкові поля, формат дати, значення `priority`, довжина `title`, коректність UTF-8 і керівні символи перевіряються в `createTask()` незалежно від HTML-атрибута `required`; `id` має бути додатним цілим числом. Некоректні дані повертають `400` з назвою поля.

**Запит без обов'язкового поля `title` (очікується `400`):**
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: ТОКЕН" -d "due_date=2026-12-01&priority=Високий"
```
<!-- скриншот -->

**Некоректний `id` (очікується `400`):**
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=-1" -H "X-CSRF-Token: ТОКЕН"
```
<!-- скриншот -->

## Крок 5. Дії, що змінюють дані, не виконуються за GET

Позначення виконаним доступне лише через `POST` і потребує токена. `GET` із параметром `action` відхиляється:

```php
if ($method === 'GET') {
    if (isset($_GET['action'])) {
        header('Allow: POST');
        fail(405, 'Дії, що змінюють дані, виконуються лише методом POST');
    }
    ...
}
```

**Перевірка (очікується `405`, завдання не змінюється):**
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&action=complete&id=3"
```
<!-- скриншот -->

У застосунку немає облікових записів, тому перевірка «належності» завдання не застосовна; захист забезпечують метод POST і CSRF-токен. Видалення в застосунку не реалізовано, тому підтвердження перед видаленням не потрібне.

## Крок 6. Обробка помилок

У `api.php` вимкнено показ помилок PHP клієнту (`ini_set('display_errors', '0')`), усі виняткові ситуації перехоплюються й повертаються як загальне повідомлення, а деталі пишуться в лог:

```php
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    fail(500, 'Внутрішня помилка сервера');
}
```

**Перевірка:** тимчасово вкажіть у `db.php` неправильну назву бази (наприклад, `dbname=nonexistent`) і виконайте запит. Клієнт отримує `500` із загальним повідомленням, а текст винятку з'являється лише в консолі, де запущено сервер. Після перевірки поверніть правильну назву.
```bash
curl -i "http://localhost:8000/api.php?resource=tasks"
```
<!-- скриншот -->

## Як запустити локально

1. Сервер PostgreSQL запущено, створена БД `practicum4`. Якщо таблиці `tasks` ще немає, виконайте `schema.sql`.
2. У папці проєкту: `php -S localhost:8000` (потрібен PHP 8.1+).
3. Сторінка: `http://localhost:8000/index.php`.

## Результат

<!-- Вставте скриншоти демонстраційної сторінки та, за потреби, DevTools (Network, Application -> Cookies) -->
