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

## Підсумок аудиту 

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

Усі SQL-запити в застосунку виконуються через `prepare()/execute()`. У текст запиту потрапляють лише константні фрагменти (`done = :done`, `priority = :priority`), значення передаються плейсхолдерами. Список усіх викликів до БД:

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
<img width="1446" height="362" alt="image" src="https://github.com/user-attachments/assets/c12f3e85-f0f2-42db-8cb7-85e6c2d9652c" />

**Перевірка payload в `id` (очікується `400`):**
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&id=1%20OR%201%3D1"
```
<img width="1406" height="330" alt="image" src="https://github.com/user-attachments/assets/608ce537-63a5-4e16-9d5c-f6827dbaa08d" />

**Payload у полі `title` (підготовлений запит зберігає його як звичайний текст, таблиця не видаляється).** Спочатку отримайте токен (див. крок 3), потім:
```bash
curl -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: ТОКЕН" --data-urlencode "title=x'); DROP TABLE tasks;--" -d "due_date=2026-12-01&priority=Високий"
```
<img width="1406" height="487" alt="image" src="https://github.com/user-attachments/assets/0721a99e-5ce2-4149-825f-f00afe9707c8" />

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

<img width="1282" height="141" alt="image" src="https://github.com/user-attachments/assets/74130b64-8eae-4294-a58a-e39cd2e7de32" />

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
<img width="1412" height="422" alt="image" src="https://github.com/user-attachments/assets/1f1b474e-1981-4865-b67b-3094406a79fd" />

**Без токена -> `403`:**
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks" -d "title=Hack&due_date=2026-12-01&priority=Високий"
```
<img width="1396" height="447" alt="image" src="https://github.com/user-attachments/assets/67c84fcd-13e5-4dc4-a1f4-b23243f9161d" />

**Недійсний (старий) токен зі справжньою сесією -> `403`:**
```bash
curl.exe -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: stale123" -d "title=Hack&due_date=2026-12-01&priority=Високий"
```
<img width="1392" height="417" alt="image" src="https://github.com/user-attachments/assets/0ca2b5e6-3d2b-4ef8-befe-9d44b1e2ab6c" />

**Доменна дія без токена -> `403` (завдання не змінюється):**
```bash
curl.exe -i -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=3"
```
<img width="1405" height="467" alt="image" src="https://github.com/user-attachments/assets/a43b9cfb-4f9a-465f-8d9f-9ec6825b43ef" />

**Правильний токен -> `201`:**
```bash
curl.exe -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: ТОКЕН" -d "title=Нове завдання&due_date=2026-12-01&priority=Високий"
```
<img width="1403" height="465" alt="image" src="https://github.com/user-attachments/assets/df844b86-c111-4518-88dd-45d77ad30c52" />

**Доменна дія з токеном -> `200`:**
```bash
curl.exe -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=5" -H "X-CSRF-Token: ТОКЕН"
```
<img width="1407" height="447" alt="image" src="https://github.com/user-attachments/assets/72505f0e-4f1f-4abc-a72e-50fdef51d47d" />

## Крок 4. Валідація на сервері

Обов'язкові поля, формат дати, значення `priority`, довжина `title`, коректність UTF-8 і керівні символи перевіряються в `createTask()` незалежно від HTML-атрибута `required`; `id` має бути додатним цілим числом. Некоректні дані повертають `400` з назвою поля.

**Запит без обов'язкового поля `title` (очікується `400`):**
```bash
curl.exe -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks" -H "X-CSRF-Token: ТОКЕН" -d "due_date=2026-12-01&priority=Високий"
```
<img width="1417" height="447" alt="image" src="https://github.com/user-attachments/assets/a09e9c69-f174-471c-b507-f6c104acc1ca" />

**Некоректний `id` (очікується `400`):**
```bash
curl.exe -i -b cookies.txt -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=-1" -H "X-CSRF-Token: ТОКЕН"
```
<img width="1425" height="418" alt="image" src="https://github.com/user-attachments/assets/d210057f-1eb9-4ee4-993d-8932e0d876ea" />

## Крок 5. Дії, що змінюють дані, не виконуються за GET

Позначення завдання виконаним (`complete`) та видалення завдання (`delete`) доступні лише через `POST` і потребують валідного CSRF-токена. Будь-який `GET`-запит із параметром `action` відхиляється сервером зі статусом `405`:
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
curl.exe -i "http://localhost:8000/api.php?resource=tasks&action=complete&id=5"
```
<img width="1402" height="376" alt="image" src="https://github.com/user-attachments/assets/6e39ab1d-15b5-4d6c-98b5-1a10509a5c30" />

У застосунку немає облікових записів, тому перевірка «належності» завдання не застосовна - захист від несанкціонованої зміни чи видалення забезпечують метод `POST` і серверна перевірка CSRF-токена. Перед видаленням завдання на клієнті (`script.js`) додатково викликається діалог підтвердження `confirm()`, який захищає користувача від випадкового натискання кнопки, але не є повноцінним захистом сам по собі, тому основний захист реалізовано на боці сервера.

<img width="1326" height="363" alt="image" src="https://github.com/user-attachments/assets/854d5149-361b-4163-8482-74bdf0572be0" />


## Крок 6. Обробка помилок

У `api.php` вимкнено показ помилок PHP клієнту (`ini_set('display_errors', '0')`), усі виняткові ситуації повертаються як загальне повідомлення, а деталі пишуться в лог:

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
<img width="1192" height="365" alt="image" src="https://github.com/user-attachments/assets/58131390-acc6-42d6-8406-023c83ab7773" />

## Як запустити локально

1. Сервер PostgreSQL запущено, створена БД `practicum4`. Якщо таблиці `tasks` ще немає, виконайте `schema.sql`.
2. У папці проєкту: `php -S localhost:8000`.
3. Сторінка: `http://localhost:8000`.

