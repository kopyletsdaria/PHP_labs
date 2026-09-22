<?php
$errors = [];
$successMessage = '';

$title = '';
$dueDate = '';
$priority = 'Середній'; 
$allowedPriorities = ['Низький', 'Середній', 'Високий'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $dueDate = trim($_POST['dueDate'] ?? '');
    $priority = trim($_POST['priority'] ?? '');
    if ($title === '') {
        $errors['title'] = 'Назва завдання є обов\'язковою.';
    }
    if ($dueDate === '') {
        $errors['dueDate'] = 'Вкажіть дату дедлайну.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $dueDate);
        if (!$d || $d->format('Y-m-d') !== $dueDate) {
            $errors['dueDate'] = 'Некоректний формат дати.';
        }
    }
    if (!in_array($priority, $allowedPriorities)) {
        $errors['priority'] = 'Оберіть коректний пріоритет зі списку.';
    }
    if (empty($errors)) {
        $successMessage = "Завдання &laquo;" . htmlspecialchars($title) . "&raquo; успішно додано!";
        $title = '';
        $dueDate = '';
        $priority = 'Середній';
        $clearStorage = true; 
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Додати нове завдання</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Додати нове завдання</h2>
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?= $successMessage ?>
            </div>
        <?php endif; ?>

        <form id="taskForm" method="post" action="form.php">
            
            <div class="form-group">
                <label for="title">Назва завдання:</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                <?php if (isset($errors['title'])): ?>
                    <span class="error-text"><?= $errors['title'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="dueDate">Дата дедлайну:</label>
                <input type="date" id="dueDate" name="dueDate" value="<?= htmlspecialchars($dueDate) ?>" required>
                <?php if (isset($errors['dueDate'])): ?>
                    <span class="error-text"><?= $errors['dueDate'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="priority">Пріоритет:</label>
                <select id="priority" name="priority" required>
                    <option value="Низький" <?= $priority === 'Низький' ? 'selected' : '' ?>>Низький</option>
                    <option value="Середній" <?= $priority === 'Середній' ? 'selected' : '' ?>>Середній</option>
                    <option value="Високий" <?= $priority === 'Високий' ? 'selected' : '' ?>>Високий</option>
                </select>
                <?php if (isset($errors['priority'])): ?>
                    <span class="error-text"><?= $errors['priority'] ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="submit-btn">Зберегти завдання</button>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById('taskForm');
            const titleInput = document.getElementById('title');
            const dateInput = document.getElementById('dueDate');
            const prioritySelect = document.getElementById('priority');
            <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                if (localStorage.getItem('draft_title')) titleInput.value = localStorage.getItem('draft_title');
                if (localStorage.getItem('draft_date')) dateInput.value = localStorage.getItem('draft_date');
                if (localStorage.getItem('draft_priority')) prioritySelect.value = localStorage.getItem('draft_priority');
            <?php endif; ?>

            form.addEventListener('input', function() {
                localStorage.setItem('draft_title', titleInput.value);
                localStorage.setItem('draft_date', dateInput.value);
                localStorage.setItem('draft_priority', prioritySelect.value);
            });
            <?php if (!empty($clearStorage)): ?>
                localStorage.removeItem('draft_title');
                localStorage.removeItem('draft_date');
                localStorage.removeItem('draft_priority');
            <?php endif; ?>
            form.addEventListener('submit', function(event) {
                if (titleInput.value.trim().length > 0 && titleInput.value.trim().length < 3) {
                    alert('Помилка: Назва завдання повинна містити хоча б 3 символи.');
                    event.preventDefault(); 
                }
            });
        });
    </script>
</body>
</html>