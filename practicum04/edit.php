<?php
require_once 'db.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
$stmt->execute([':id' => $id]);
$task = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateStmt = $pdo->prepare('UPDATE tasks SET title = :title, due_date = :due_date, priority = :priority WHERE id = :id');
    $updateStmt->execute([
        ':title' => $_POST['title'],
        ':due_date' => $_POST['due_date'],
        ':priority' => $_POST['priority'],
        ':id' => $id
    ]);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Редагувати завдання</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 500px;">
        <h2>Редагувати завдання</h2>
        <form action="" method="POST" class="add-form" style="flex-direction: column;">
            <input type="text" name="title" value="<?= htmlspecialchars($task['title']) ?>" required>
            <input type="date" name="due_date" value="<?= htmlspecialchars($task['due_date']) ?>" required>
            <select name="priority" required>
                <option value="Низький" <?= $task['priority'] == 'Низький' ? 'selected' : '' ?>>Низький</option>
                <option value="Середній" <?= $task['priority'] == 'Середній' ? 'selected' : '' ?>>Середній</option>
                <option value="Високий" <?= $task['priority'] == 'Високий' ? 'selected' : '' ?>>Високий</option>
            </select>
            <button type="submit" class="submit-btn">Зберегти</button>
            <a href="index.php" style="text-align: center; margin-top: 10px; color: #d81b60;">Скасувати</a>
        </form>
    </div>
</body>
</html>