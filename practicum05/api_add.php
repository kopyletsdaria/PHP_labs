<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $priority = $_POST['priority'] ?? '';

    if ($title && $due_date && $priority) {
        try {
            $stmt = $pdo->prepare('INSERT INTO tasks (title, due_date, priority, done) VALUES (:title, :due_date, :priority, 0) RETURNING *');
            $stmt->execute([':title' => $title, ':due_date' => $due_date, ':priority' => $priority]);
            $newTask = $stmt->fetch();
            http_response_code(201);
            echo json_encode($newTask);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Помилка БД']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Не всі поля заповнені']);
    }
}