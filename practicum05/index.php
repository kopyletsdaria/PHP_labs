<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>AJAX Трекер завдань</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Додати нове завдання</h2>
        <div id="message-box" style="color: #c62828; margin-bottom: 10px;"></div>
        
        <form id="addForm" class="add-form">
            <input type="text" name="title" id="title" placeholder="Назва завдання" required>
            <input type="date" name="due_date" id="due_date" required>
            <select name="priority" id="priority" required>
                <option value="Низький">Низький</option>
                <option value="Середній">Середній</option>
                <option value="Високий">Високий</option>
            </select>
            <button type="submit" class="submit-btn">Додати</button>
        </form>

        <h2>Список завдань</h2>
        <div class="filter-block">
            <label>Жива фільтрація:</label>
            <select id="priorityFilter">
                <option value="">Усі пріоритети</option>
                <option value="Низький">Низький</option>
                <option value="Середній">Середній</option>
                <option value="Високий">Високий</option>
            </select>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Виконано</th>
                    <th>Назва</th>
                    <th>Дедлайн</th>
                    <th>Пріоритет</th>
                </tr>
            </thead>
            <tbody id="results"></tbody>
        </table>
    </div>
    <script src="script.js"></script>
</body>
</html>