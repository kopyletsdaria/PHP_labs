<?php
$dsn = 'pgsql:host=localhost;port=5432;dbname=practicum4';

try {
    $pdo = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Помилка підключення до БД: " . $e->getMessage());
}
?>