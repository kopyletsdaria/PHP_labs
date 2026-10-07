<?php
require_once 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO tasks (title, due_date, priority) VALUES (:title, :due_date, :priority)');
    $stmt->execute([
        ':title' => $_POST['title'],
        ':due_date' => $_POST['due_date'],
        ':priority' => $_POST['priority']
    ]);
    header('Location: index.php');
    exit;
}