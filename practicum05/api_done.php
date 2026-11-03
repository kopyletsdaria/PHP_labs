<?php
require_once 'db.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id']) && isset($data['done'])) {
    $stmt = $pdo->prepare('UPDATE tasks SET done = :done WHERE id = :id');
    $stmt->execute([':done' => $data['done'], ':id' => $data['id']]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Некоректні дані']);
}