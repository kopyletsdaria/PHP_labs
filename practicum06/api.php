<?php
/*
 * Контракт (ресурс tasks: id, title, due_date, priority, done):
 *   GET  api.php?resource=tasks
 *        -> 200, список завдань; необов'язковий параметр done=0|1 (фільтр)
 *   GET  api.php?resource=tasks&id=N
 *        -> 200, одне завдання | 400 (некоректний id) | 404 (не знайдено)
 *   POST api.php?resource=tasks
 *        параметри: title, due_date (YYYY-MM-DD), priority (Низький|Середній|Високий)
 *        -> 201, створене завдання | 400 (поле відсутнє або некоректне)
 *   POST api.php?resource=tasks&action=complete&id=N
 *        -> 200, завдання з done = 1 | 400 | 404
 *   інші методи для tasks -> 405 (заголовок Allow: GET, POST)
 *   невідомий resource    -> 404
 *
 * Формат відповіді:
 *   успіх:   {"success": true, "data": {"id": 9, "title": "Нове завдання",
 *             "due_date": "2026-11-20", "priority": "Високий", "done": 0}}
 *   помилка: {"success": false, "error": "опис помилки", "field": "назва поля"}
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
const PRIORITIES = ['Низький', 'Середній', 'Високий'];

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

function findTask(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, due_date, priority, done FROM tasks WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : normalizeTask($row);
}

function listTasks(PDO $pdo): never
{
    $sql = 'SELECT id, title, due_date, priority, done FROM tasks';
    $params = [];

    if (isset($_GET['done'])) {
        if ($_GET['done'] !== '0' && $_GET['done'] !== '1') {
            fail(400, 'Поле done має бути 0 або 1', 'done');
        }
        $sql .= ' WHERE done = :done';
        $params['done'] = (int) $_GET['done'];
    }

    $sql .= ' ORDER BY done ASC, due_date ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $tasks = array_map('normalizeTask', $stmt->fetchAll());
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

    $stmt = $pdo->prepare('UPDATE tasks SET done = 1 WHERE id = :id');
    $stmt->execute(['id' => $id]);

    respond(200, ['success' => true, 'data' => findTask($pdo, $id)]);
}

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? null;

try {
    if ($resource !== 'tasks') {
        fail(404, 'Невідомий ресурс. Доступний ресурс: tasks');
    }

    require __DIR__ . '/db.php';

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
    error_log('API DB error: ' . $e->getMessage());
    fail(500, 'Внутрішня помилка сервера');
}
