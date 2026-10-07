<?php
require_once 'db.php'; 
$priorityFilter = $_GET['priority'] ?? '';
if ($priorityFilter) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE priority = :priority");
    $stmt->execute([':priority' => $priorityFilter]);
} else {
    $stmt = $pdo->query("SELECT * FROM tasks ORDER BY due_date ASC");
}
$tasks = $stmt->fetchAll(); 
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Трекер завдань (БД)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Додати нове завдання</h2>
        <form action="add.php" method="POST" class="add-form">
            <input type="text" name="title" placeholder="Назва завдання" required>
            <input type="date" name="due_date" required>
            <select name="priority" required>
                <option value="Низький">Низький</option>
                <option value="Середній">Середній</option>
                <option value="Високий">Високий</option>
            </select>
            <button type="submit" class="submit-btn">Додати</button>
        </form>

        <h2>Список завдань</h2>
        <div class="filter-block">
            <form action="index.php" method="GET">
                <label>Фільтр:</label>
                <select name="priority">
                    <option value="">Усі</option>
                    <option value="Низький" <?= $priorityFilter == 'Низький' ? 'selected' : '' ?>>Низький</option>
                    <option value="Середній" <?= $priorityFilter == 'Середній' ? 'selected' : '' ?>>Середній</option>
                    <option value="Високий" <?= $priorityFilter == 'Високий' ? 'selected' : '' ?>>Високий</option>
                </select>
                <button type="submit" class="submit-btn" style="width: auto; padding: 5px 15px;">Застосувати</button>
            </form>
        </div>

        <table>
            <tr>
                <th>ID</th>
                <th>Назва</th>
                <th>Дедлайн</th>
                <th>Пріоритет</th>
                <th>Статус</th>
                <th>Дії</th>
            </tr>
            <?php foreach ($tasks as $task): ?>
            <tr class="<?= $task['done'] ? 'done-row' : '' ?>">
                <td><?= $task['id'] ?></td>
                <td><?= htmlspecialchars($task['title']) ?></td>
                <td><?= htmlspecialchars($task['due_date']) ?></td>
                <td><?= htmlspecialchars($task['priority']) ?></td>
                <td><?= $task['done'] ? 'Виконано' : 'В роботі' ?></td>
                <td class="actions">
                    <?php if (!$task['done']): ?>
                        <a href="mark_done.php?id=<?= $task['id'] ?>" class="btn-done">Виконати</a>
                    <?php endif; ?>
                    <a href="edit.php?id=<?= $task['id'] ?>" class="btn-edit">Редагувати</a>
                    <a href="delete.php?id=<?= $task['id'] ?>" class="btn-delete" onclick="return confirm('Дійсно видалити завдання?');">Видалити</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>