# Практикум 4: Взаємодія вебзастосунку з БД PostgreSQL (Варіант 7)

**Виконала:** Копилець Дар'я, група ІО-44

## Структура проєкту

*   `db.php` - файл конфігурації для створення об'єкта підключення PDO до БД.
*   `index.php` - головна сторінка з HTML-таблицею (SELECT) та формою для додавання завдань.
*   `add.php` - скрипт-обробник для додавання нового запису в таблицю (INSERT).
*   `edit.php` - форма та скрипт для редагування існуючого запису (UPDATE).
*   `delete.php` - скрипт для видалення запису з бази даних (DELETE).
*   `mark_done.php` - скрипт для швидкого оновлення статусу завдання.
*   `style.css` - файл стилів.

## Реалізовані вимоги

1. **Створення БД і таблиці**: у pgAdmin створено базу `practicum4` і таблицю `tasks` із первинним ключем `id` типу `SERIAL` та стовпцями `title`, `due_date`, `priority`, `done`.
2. **Підключення до БД**: у файлі `db.php` реалізовано підключення через PDO з DSN для PostgreSQL (`pgsql:host=localhost...`) та налаштуваннями `ERRMODE_EXCEPTION` і `FETCH_ASSOC`.
3. **Виведення записів (SELECT)**: реалізовано метод `fetchAll()` для отримання всіх завдань. Додано фільтрацію за пріоритетом `findByPriority($priority)`.
4. **Додавання запису (INSERT)**: створено HTML-форму та скрипт `add.php`, який додає дані в базу використовуючи підготовлені запити (`prepare` + `execute`).
5. **Редагування запису (UPDATE)**: реалізовано посилання "Редагувати", яке відкриває заповнену форму. Зміни зберігаються за допомогою підготовленого запиту з умовою `WHERE id = :id`.
6. **Видалення запису (DELETE)**: реалізовано видалення запису через підготовлений запит. Перед видаленням спрацьовує JavaScript-запит на підтвердження `confirm()`.
7. **Захист від SQL-ін'єкцій**: усі параметри від користувача (`$_POST`, `$_GET`) передаються виключно через плейсхолдери, тому порушення структури SQL-запиту неможливе.

## Як запустити локально

1. Локальний сервер PostgreSQL повинен бути запущеним, а драйвери `pdo_pgsql` увімкнено у файлі `php.ini`.
2. Створіть базу даних `practicum4` у pgAdmin і виконайте SQL-запит для створення таблиці `tasks`.
3. Оновіть пароль у файлі `db.php` на власний.
4. Відкрийте термінал у головній папці `php_labs` та запустіть сервер:

   ```bash
   php -S localhost:8000
    ```
## Результат
<img width="1323" height="630" alt="image" src="https://github.com/user-attachments/assets/116d71d9-5ead-4843-84f4-abfa59ab8b4a" />
<img width="1170" height="646" alt="image" src="https://github.com/user-attachments/assets/904669fd-a4c9-45e5-9bd2-2fc4ba51da6b" />
<img width="882" height="502" alt="image" src="https://github.com/user-attachments/assets/46ec14b3-7a12-469f-b942-f7c8edbd1fa8" />
<img width="1116" height="685" alt="image" src="https://github.com/user-attachments/assets/90cada7c-643a-44c5-b3c0-dac3963a36bc" />

При натисканні "Виконати":

<img width="1171" height="591" alt="image" src="https://github.com/user-attachments/assets/645e9960-f397-41f4-8188-86b32895dc77" />

При натисканні "Тільки невиконані":

<img width="1256" height="592" alt="image" src="https://github.com/user-attachments/assets/b7806db4-66a0-4dff-acf1-d5999e7fdd77" />





