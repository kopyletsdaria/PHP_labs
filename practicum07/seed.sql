-- Тестові дані для вимірювань - 500 завдань
-- Розподіл пріоритетів нерівномірний- Високий ~2%, Середній ~24%, Низький ~74%
-- (щоб EXPLAIN після створення індексу показав різницю). Половина завдань невиконані.
INSERT INTO tasks (title, due_date, priority, done)
SELECT 'Тестове завдання ' || g,
       CURRENT_DATE + (g % 60),
       CASE WHEN g % 50 = 0 THEN 'Високий'
            WHEN g % 4 = 0 THEN 'Середній'
            ELSE 'Низький' END,
       (g % 2)
FROM generate_series(1, 500) AS g;

ANALYZE tasks;


