<?php
require_once 'db.php';
header('Content-Type: application/json');

$priority = $_GET['priority'] ?? '';

try {
    if ($priority) {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE priority = :priority ORDER BY due_date ASC");
        $stmt->execute([':priority' => $priority]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $pdo->query("SELECT * FROM tasks ORDER BY due_date ASC")->fetchAll();
    }
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Помилка сервера']);
}