<?php
require_once 'classes/Task.php';
require_once 'classes/RecurringTask.php';
require_once 'classes/ProjectBoard.php';
require_once 'lib/functions.php';
$board = new ProjectBoard();
$board->addTask(new Task("Підготувати звіт з практики", "2026-09-10", "Високий"));
$board->addTask(new RecurringTask("Піти на прогулянку", "2026-09-25", "Низький", "Кожні вихідні"));
$board->addTask(new Task("Проаналізувати датасет у Pandas", "2026-09-05", "Високий", true));
$board->addTask(new RecurringTask("Відвідати лекцію з PHP", "2026-09-28", "Середній", "Щотижня у вівторок"));
$board->markDone("Підготувати звіт з практики");
$allTasks = $board->getAllTasks();
$pendingTasks = $board->pendingTasks();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ООП Трекер завдань</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Усі завдання</h2>
        <table>
            <thead>
                <tr>
                    <th>Опис</th>
                    <th>Дедлайн </th>
                    <th>Прострочено?</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allTasks as $task): ?>
                    <tr>
                        <td><?= $task->getInfo() ?></td>
                        <td><?= formatDateUA($task->getDueDate()) ?></td>
                        <td>
                            <?php if (!$task->isDone() && isOverdue($task->getDueDate())): ?>
                                <span style="color: #c62828; font-weight: bold;">Так!</span>
                            <?php else: ?>
                                <span style="color: #2e7d32;">Ні</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Невиконані завдання:</h3>
        <ul>
            <?php foreach ($pendingTasks as $task): ?>
                <li><?= $task->getTitle() ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</body>
</html>