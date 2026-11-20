# Практикум 7: Експерименти з підвищеним навантаженням: оптимізація серверних скриптів (Варіант 7)

**Виконала:** Копилець Дар'я, група ІО-44

## Структура проєкту

*   `api.php` - API трекера завдань (з практикуму №6) з профілюванням, режимами порівняння та кешем.
*   `db.php` - підключення до PostgreSQL (PDO) з лічильником SQL-запитів.
*   `profiler.php` - клас `Profiler` (час, пам'ять, лічильник запитів) і обгортки `CountingPDO` / `CountingStatement`.
*   `seed.sql` - тестові дані (500 завдань) для вимірювань.
*   `index.php`, `script.js`, `style.css` - демонстраційний клієнт без змін.

## Завдання варіанта 7

*   **Навантаження:** списковий ендпоінт `api.php?resource=tasks&done=0`.
*   **Індекс:** стовпець `priority`.
*   **N+1:** для кожного завдання окремий запит кількості завдань з таким самим `priority` -> один запит із `GROUP BY priority`.
*   **Кеш:** `pendingTasks()` - список невиконаних завдань (файловий кеш, TTL 30 секунд, скидається при додаванні та виконанні завдання).

## Підготовка

1. Сервер PostgreSQL запущено, наявна БД `practicum4` з таблицею `tasks`.
2. Виконайте файл `seed.sql` у pgAdmin (Query Tool) або через `psql` - додасться 500 тестових завдань.
3. У папці проєкту запустіть сервер: `php -S localhost:8000`.

## Вимірювання

Команди виконуються в терміналі запущеного сервера. Значення часу, пам'яті та кількості запитів беруться із заголовків відповіді `curl -i`.

### Крок 1, 3. Базова лінія до оптимізацій (час, пам'ять, кількість SQL-запитів)

```bash
curl -i "http://localhost:8000/api.php?resource=tasks&done=0&mode=slow"
```
<img width="1457" height="702" alt="image" src="https://github.com/user-attachments/assets/e83c6712-362c-45fc-8ccb-a54163ba2818" />

### Крок 2. Навантажувальне тестування до оптимізацій

```bash
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=tasks&done=0&mode=slow"
```
<img width="1196" height="950" alt="image" src="https://github.com/user-attachments/assets/fda968d1-f0c9-449e-b210-be9728b0d556" />

### Крок 4. Індекс на `priority` (EXPLAIN до і після)

До індексу:
```sql
EXPLAIN SELECT * FROM tasks WHERE priority = 'Високий';
```
<img width="488" height="112" alt="image" src="https://github.com/user-attachments/assets/71b1a84b-acdb-4f81-b4ce-27f7b28b996d" />

Створення індексу:
```sql
CREATE INDEX idx_tasks_priority ON tasks (priority);
ANALYZE tasks;
```

Після індексу:
```sql
EXPLAIN SELECT * FROM tasks WHERE priority = 'Високий';
```
<img width="655" height="177" alt="image" src="https://github.com/user-attachments/assets/539a0081-026d-4c9f-904c-8778ca4190c7" />


### Крок 5. Без N+1 (лічильник запитів зменшився)

```bash
curl -i "http://localhost:8000/api.php?resource=tasks&done=0&mode=nocache"
```
<img width="1470" height="610" alt="image" src="https://github.com/user-attachments/assets/deeb0ebe-1456-4c6e-aa02-92220cd1211f" />

### Крок 6. Кешування `pendingTasks()`

Перший запит (кеш порожній, `X-Cache: MISS`):
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&done=0"
```
<img width="557" height="247" alt="image" src="https://github.com/user-attachments/assets/d7b01085-c050-423b-b980-6eb65a30097b" />

Повторний запит (`X-Cache: HIT`, `X-SQL-Queries: 0`):
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&done=0"
```
<img width="561" height="237" alt="image" src="https://github.com/user-attachments/assets/1e968c69-6501-4f9b-81fd-717788defa30" />

Зміна даних скидає кеш (створення завдання):
```bash
curl -i -X POST "http://localhost:8000/api.php?resource=tasks" -d "title=Перевірка кешу&due_date=2026-11-30&priority=Високий"
```
<img width="567" height="267" alt="image" src="https://github.com/user-attachments/assets/65a769f9-0e54-4d7f-8227-a1b9c5cfc93c" />

Наступний запит знову `MISS` і вже містить нове завдання:
```bash
curl -i "http://localhost:8000/api.php?resource=tasks&done=0"
```
<img width="572" height="253" alt="image" src="https://github.com/user-attachments/assets/2b69e2ac-82a0-4013-8c62-fea4de710355" />

### Крок 7. Навантажувальне тестування після оптимізацій

```bash
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=tasks&done=0"
```
<img width="906" height="937" alt="image" src="https://github.com/user-attachments/assets/e1417110-c34c-41f2-bf4c-643f2dc5674c" />

## Звіт «до / після»

| Показник | До (`mode=slow`, без індексу) | Після (індекс + `GROUP BY` + кеш) |
| :--- | :---: | :---: |
| Час виконання одного запиту, мс | 166.61 | 31.03 (HIT) / 37.32 (MISS) |
| Пікова пам'ять, МБ | 2.00 | 2.00 |
| SQL-запитів на один виклик | 253 | 0 (HIT) / 2 (MISS) |
| Requests per second | 6.05 | 22.87 |
| Time per request (mean), мс | 3305.87 | 874.33 |
| Failed requests | 0 | 0 |


**Пояснення різниці:** Продуктивність зросла майже в 4 рази (з 6.05 до 22.87), так як заміна 251 запиту в циклі (проблема N+1) на один агрегований запит із GROUP BY, додавання індексу на стовпець priority та файлове кешування скоротили кількість звернень до бази даних із 253 до 2 (а при попаданні в кеш - до 0).
