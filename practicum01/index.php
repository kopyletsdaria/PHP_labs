<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$tasks = [
    [
        'title' => 'Проаналізувати датасет у Pandas', 
        'dueDate' => '2026-09-05', 
        'priority' => 'Високий', 
        'done' => true
    ],
    [
        'title' => 'Підготувати лабораторну роботу з PHP', 
        'dueDate' => '2026-09-10', 
        'priority' => 'Високий', 
        'done' => false
    ],
    [
        'title' => 'Зіграти в шахи онлайн', 
        'dueDate' => '2026-09-15', 
        'priority' => 'Низький', 
        'done' => false
    ],
    [
        'title' => 'Доробити скрипт мовою Python', 
        'dueDate' => '2026-09-20', 
        'priority' => 'Середній', 
        'done' => false
    ]
];
function formatTaskDescription(array $task): string {
    return "<strong>{$task['title']}</strong> <br><span class='meta'>Пріоритет: {$task['priority']} | Дедлайн: {$task['dueDate']}</span>";
}

$completedCount = count(array_filter($tasks, fn($t) => $t['done'] === true));
$uncompletedCount = count($tasks) - $completedCount;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Трекер завдань</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Список завдань</h2>
        <table>
            <thead>
                <tr>
                    <th>Опис завдання</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <?php
                        $statusText = '';
                        $statusClass = '';
                        $currentDate = date('Y-m-d');
                        
                        if ($task['done']) {
                            $statusText = 'Виконано';
                            $statusClass = 'status-done';
                        } elseif ($task['dueDate'] < $currentDate) {
                            $statusText = 'Прострочено';
                            $statusClass = 'status-overdue';
                        } else {
                            $statusText = 'В роботі';
                            $statusClass = 'status-progress';
                        }
                    ?>
                    <tr>
                        <td><?= formatTaskDescription($task) ?></td>
                        <td class="status-cell"><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="stats-block">
            <h3>Агрегатні показники:</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?= $completedCount ?></span>
                    <span class="stat-label">Виконаних</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?= $uncompletedCount ?></span>
                    <span class="stat-label">Невиконаних</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>