<?php
/*
 * API трекера завдань (варіант 7)
 *
 *   GET  api.php?resource=tasks[&done=0|1][&mode=slow|nocache]  -> 200, список (з priority_count)
 *   GET  api.php?resource=tasks&id=N                            -> 200 | 400 | 404
 *   POST api.php?resource=tasks                                 -> 201 | 400
 *        поля: title, due_date (YYYY-MM-DD), priority (Низький|Середній|Високий)
 *   POST api.php?resource=tasks&action=complete&id=N            -> 200 | 400 | 404
 *   інші методи -> 405, невідомий resource -> 404
 *
 * Відповідь: {"success": true, "data": ...} або {"success": false, "error": "...", "field": "..."}
 * mode=slow - N+1 без кешу (для порівняння), mode=nocache - без кешу.
 * Заголовки X-Time-Ms, X-Peak-Memory-Mb, X-SQL-Queries, X-Cache - діагностика (практикум №7).
 */

declare(strict_types=1);

require_once __DIR__ . '/profiler.php';
Profiler::start();

header('Content-Type: application/json; charset=utf-8');

const PRIORITIES = ['Низький', 'Середній', 'Високий'];
const CACHE_TTL = 30; // секунд
const CACHE_KEY_PENDING = 'pending_tasks';

function respond(int $status, array $body): never
{
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $elapsed = Profiler::elapsedMs();
    $peakMemory = Profiler::peakMemoryMb();

    http_response_code($status);
    header(sprintf('X-Time-Ms: %.2f', $elapsed));
    header(sprintf('X-Peak-Memory-Mb: %.2f', $peakMemory));
    header('X-SQL-Queries: ' . Profiler::$queries);
    header('X-Cache: ' . Profiler::$cache);
    error_log(sprintf(
        'Час: %.2f мс, пам\'ять: %.2f МБ, SQL-запитів: %d, кеш: %s',
        $elapsed,
        $peakMemory,
        Profiler::$queries,
        Profiler::$cache
    ));

    echo $json;
    exit;
}

function fail(int $status, string $error, ?string $field = null): never
{
    $body = ['success' => false, 'error' => $error];
    if ($field !== null) {
        $body['field'] = $field;
    }
    respond($status, $body);
}

function normalizeTask(array $row): array
{
    return [
        'id'       => (int) $row['id'],
        'title'    => $row['title'],
        'due_date' => $row['due_date'],
        'priority' => $row['priority'],
        'done'     => (int) $row['done'],
    ];
}

// Дані запиту: JSON-тіло (php://input) або звичайна форма ($_POST).
function readInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            fail(400, 'Некоректний JSON у тілі запиту');
        }
        return $data;
    }
    return $_POST;
}

function parseId(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        fail(400, 'Поле id має бути додатним цілим числом', 'id');
    }
    return $id;
}

// Файловий кеш з TTL 

function cacheFile(string $key): string
{
    return sys_get_temp_dir() . '/tasks_cache_' . md5($key) . '.json';
}

function cacheGet(string $key): ?array
{
    $file = cacheFile($key);
    if (is_file($file) && (time() - filemtime($file)) < CACHE_TTL) {
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
    return null;
}

function cachePut(string $key, array $data): void
{
    file_put_contents(cacheFile($key), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Викликається після додавання та зміни завдань
function cacheClear(string $key): void
{
    $file = cacheFile($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

// Запити до БД 
function findTask(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, due_date, priority, done FROM tasks WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : normalizeTask($row);
}

function fetchTasks(PDO $pdo, ?int $done): array
{
    $sql = 'SELECT id, title, due_date, priority, done FROM tasks';
    $params = [];

    if ($done !== null) {
        $sql .= ' WHERE done = :done';
        $params['done'] = $done;
    }

    $sql .= ' ORDER BY done ASC, due_date ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('normalizeTask', $stmt->fetchAll());
}

// Повільно (N+1): окремий запит для кожного завдання
function attachCountsSlow(PDO $pdo, array $tasks): array
{
    foreach ($tasks as &$task) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE priority = :priority');
        $stmt->execute(['priority' => $task['priority']]);
        $task['priority_count'] = (int) $stmt->fetchColumn();
    }
    unset($task);
    return $tasks;
}

// Швидко: один запит із GROUP BY замість запиту в циклі
function attachCounts(PDO $pdo, array $tasks): array
{
    $counts = $pdo->query('SELECT priority, COUNT(*) AS cnt FROM tasks GROUP BY priority')
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($tasks as &$task) {
        $task['priority_count'] = (int) ($counts[$task['priority']] ?? 0);
    }
    unset($task);
    return $tasks;
}

// Список невиконаних завдань 
function pendingTasks(PDO $pdo, bool $useCache = true): array
{
    if ($useCache) {
        $cached = cacheGet(CACHE_KEY_PENDING);
        if ($cached !== null) {
            Profiler::$cache = 'HIT';
            return $cached;
        }
        Profiler::$cache = 'MISS';
    }

    $tasks = attachCounts($pdo, fetchTasks($pdo, 0));

    if ($useCache) {
        cachePut(CACHE_KEY_PENDING, $tasks);
    }
    return $tasks;
}

// Обробники 

function listTasks(PDO $pdo): never
{
    $done = null;
    if (isset($_GET['done'])) {
        if ($_GET['done'] !== '0' && $_GET['done'] !== '1') {
            fail(400, 'Поле done має бути 0 або 1', 'done');
        }
        $done = (int) $_GET['done'];
    }

    $mode = $_GET['mode'] ?? null;
    if ($mode !== null && $mode !== 'slow' && $mode !== 'nocache') {
        fail(400, 'Поле mode має бути slow або nocache', 'mode');
    }

    if ($mode === 'slow') {
        $tasks = attachCountsSlow($pdo, fetchTasks($pdo, $done));
    } elseif ($done === 0) {
        $tasks = pendingTasks($pdo, $mode !== 'nocache');
    } else {
        $tasks = attachCounts($pdo, fetchTasks($pdo, $done));
    }

    respond(200, ['success' => true, 'data' => $tasks]);
}

function getTask(PDO $pdo, int $id): never
{
    $task = findTask($pdo, $id);
    if ($task === null) {
        fail(404, "Завдання з id=$id не знайдено");
    }
    respond(200, ['success' => true, 'data' => $task]);
}

function createTask(PDO $pdo, array $input): never
{
    $title    = isset($input['title']) && is_scalar($input['title']) ? trim((string) $input['title']) : '';
    $dueDate  = isset($input['due_date']) && is_scalar($input['due_date']) ? trim((string) $input['due_date']) : '';
    $priority = isset($input['priority']) && is_scalar($input['priority']) ? trim((string) $input['priority']) : '';

    if ($title === '') {
        fail(400, 'Обов\'язкове поле title відсутнє або порожнє', 'title');
    }
    if ((preg_match_all('/./su', $title) ?: 0) > 255) {
        fail(400, 'Поле title не може бути довшим за 255 символів', 'title');
    }

    if ($dueDate === '') {
        fail(400, 'Обов\'язкове поле due_date відсутнє', 'due_date');
    }
    $date = DateTime::createFromFormat('!Y-m-d', $dueDate);
    if ($date === false || $date->format('Y-m-d') !== $dueDate) {
        fail(400, 'Поле due_date має бути коректною датою у форматі YYYY-MM-DD', 'due_date');
    }

    if ($priority === '') {
        fail(400, 'Обов\'язкове поле priority відсутнє', 'priority');
    }
    if (!in_array($priority, PRIORITIES, true)) {
        fail(400, 'Поле priority має бути одним із: ' . implode(', ', PRIORITIES), 'priority');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tasks (title, due_date, priority, done)
         VALUES (:title, :due_date, :priority, 0) RETURNING id'
    );
    $stmt->execute(['title' => $title, 'due_date' => $dueDate, 'priority' => $priority]);

    cacheClear(CACHE_KEY_PENDING); // дані змінилися - скидаємо кеш

    $id = (int) $stmt->fetchColumn();
    header('Location: api.php?resource=tasks&id=' . $id);
    respond(201, ['success' => true, 'data' => findTask($pdo, $id)]);
}

function completeTask(PDO $pdo, mixed $rawId): never
{
    if ($rawId === null) {
        fail(400, 'Для action=complete потрібен параметр id', 'id');
    }
    $id = parseId($rawId);

    if (findTask($pdo, $id) === null) {
        fail(404, "Завдання з id=$id не знайдено");
    }

    // Повторне виконання безпечне: done залишається 1.
    $stmt = $pdo->prepare('UPDATE tasks SET done = 1 WHERE id = :id');
    $stmt->execute(['id' => $id]);

    cacheClear(CACHE_KEY_PENDING); 

    respond(200, ['success' => true, 'data' => findTask($pdo, $id)]);
}

// Єдина точка входу та маршрутизація 

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;

try {
    if ($resource !== 'tasks') {
        fail(404, 'Невідомий ресурс. Доступний ресурс: tasks');
    }

    require __DIR__ . '/db.php'; // створює $pdo

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            getTask($pdo, parseId($_GET['id']));
        }
        listTasks($pdo);
    } elseif ($method === 'POST') {
        $input  = readInput();
        $action = $_GET['action'] ?? ($input['action'] ?? null);

        if ($action === null || $action === '') {
            createTask($pdo, $input);
        } elseif ($action === 'complete') {
            completeTask($pdo, $_GET['id'] ?? ($input['id'] ?? null));
        } else {
            fail(400, 'Невідома дія. Підтримується: complete', 'action');
        }
    } else {
        header('Allow: GET, POST');
        fail(405, "Метод $method не підтримується для ресурсу tasks");
    }
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    fail(500, 'Внутрішня помилка сервера');
}