# Практикум 6: Розробка власного прикладного програмного інтерфейсу (API) (Варіант 7)

**Виконала:** Копилець Дар'я, група ІО-44

## Структура проєкту

*   `api.php` - єдина точка входу API: маршрутизація за методом і параметрами URL, валідація, JSON-відповіді з кодами статусу. Контракт API описано коментарем на початку файлу.
*   `db.php` - підключення до PostgreSQL (PDO).
*   `index.php` - демонстраційна сторінка-клієнт для API.
*   `script.js` - фронтенд-логіка демонстраційного клієнта (fetch, малювання DOM, обробка помилок).
*   `style.css` - файл стилів.

## Контракт API

Ресурс: `tasks` (`id, title, due_date, priority, done`). Усі запити йдуть на `api.php`.

*   `GET api.php?resource=tasks` - список завдань (необов'язковий фільтр `&done=0` або `&done=1`), код `200`.
*   `GET api.php?resource=tasks&id=N` - одне завдання, код `200`, або `404`, якщо його немає.
*   `POST api.php?resource=tasks` - створення завдання (`title`, `due_date`, `priority`), код `201` або `400`.
*   `POST api.php?resource=tasks&action=complete&id=N` - позначити завдання виконаним, код `200`, `400` або `404`.
*   Інші методи - `405`, невідомий ресурс - `404`.

Усі відповіді мають однакову структуру:

```json
{"success": true, "data": {"id": 9, "title": "Нове завдання", "due_date": "2026-11-20", "priority": "Високий", "done": 0}}
```

```json
{"success": false, "error": "Поле priority має бути одним із: Низький, Середній, Високий", "field": "priority"}
```

## Реалізовані вимоги

1. **Контракт API**: перелік запитів описано в цьому файлі та коментарем на початку `api.php`.
2. **Єдина точка входу**: усі запити надходять на `api.php`, який одразу встановлює `Content-Type: application/json` і визначає `REQUEST_METHOD` та `$_GET['resource']`.
3. **Маршрутизація**: за допомогою `if/elseif` визначається комбінація метод + ресурс (+ `action`) і викликається відповідна функція-обробник. Невідомий ресурс повертає `404` та JSON з описом помилки.
4. **GET**: список повертає `200`; запит із `id` повертає одне завдання або `404`, якщо такого `id` немає в таблиці.
5. **POST (створення)**: дані приймаються з `$_POST` або JSON-тіла (`php://input`). Обов'язкові поля перевіряються (`400` із вказанням некоректного поля), далі виконується `INSERT` через підготовлений запит, відповідь - `201` з даними створеного запису.
6. **Доменна дія**: `POST ... &action=complete&id=N` позначає завдання виконаним (`done = 1`). Неіснуючий `id` дає `404`, некоректний - `400`.
7. **Некоректні запити**: непідтримуваний метод - `405`; некоректні параметри та поля - `400` із описом, яке саме поле не пройшло перевірку.
8. **Ручна перевірка**: маршрути перевірено браузером (GET) та `curl -i` (POST, 400, 404, 405) - див. розділ «Результат».

## Як запустити локально

1. Сервер PostgreSQL запущено та наявна БД `practicum4` з таблицею `tasks` (з попередніх робіт).
2. Відкрийте термінал у папці проєкту та запустіть вбудований сервер PHP (потрібен PHP 8.1+):
   ```bash
   php -S localhost:8000
   ```

## Приклади перевірки

Команди виконуються в терміналі (cmd) за запущеного сервера `php -S localhost:8000`. Перед запуском: `chcp 65001`.

**200 - список**
```bash
curl -i "http://localhost:8000/api.php?resource=tasks"
```
<img width="1433" height="322" alt="image" src="https://github.com/user-attachments/assets/7667f363-0f96-428c-ae30-f6d4bb132a4d" />

**200 - одне завдання**
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&id=7"
```
<img width="1371" height="210" alt="image" src="https://github.com/user-attachments/assets/b708d786-6eb9-4dfe-8bfe-b8e82ee139d7" />

**404 - неіснуюче завдання**
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&id=999"
```
<img width="931" height="217" alt="image" src="https://github.com/user-attachments/assets/af1c92e6-9f59-4948-b3b1-ccc1a80e18cb" />

**201 - створення**
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks" -d "title=Нове завдання&due_date=2026-11-20&priority=Високий"
```
<img width="1453" height="260" alt="image" src="https://github.com/user-attachments/assets/d0d8e1c5-78b5-45e3-aeef-06b8e7e7130c" />

**400 - не вистачає обов'язкового поля**
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks" -d "title=Тест&priority=Високий"
```
<img width="1310" height="212" alt="image" src="https://github.com/user-attachments/assets/84a231e6-b03b-4139-a2b1-53d57c307b77" />

**200 - доменна дія (позначити виконаним)**
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks&action=complete&id=9"
```
<img width="1332" height="210" alt="image" src="https://github.com/user-attachments/assets/4265051d-1ca5-4d11-9ed3-7ecf844c4355" />

**405 - непідтримуваний метод**
```bash
curl -i -X DELETE "http://localhost:8000/api.php?resource=tasks&id=7"
```
<img width="1006" height="235" alt="image" src="https://github.com/user-attachments/assets/3a06763e-5f8e-41a2-9793-24dede35fce2" />

**404 - невідомий ресурс**
```bash
curl -i "http://localhost:8000/api.php?resource=books"
```
<img width="841" height="212" alt="image" src="https://github.com/user-attachments/assets/36123d5d-ca95-4bc6-a379-85fe573d668d" />

## Результат

<img width="807" height="616" alt="image" src="https://github.com/user-attachments/assets/f25a06eb-47ab-422b-a688-d2500f46a0a6" />

<img width="847" height="275" alt="image" src="https://github.com/user-attachments/assets/1846fad5-f41e-4ec0-aa49-848f1642eaca" />

