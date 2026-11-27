<?php
require_once __DIR__ . '/profiler.php';

$dsn = 'pgsql:host=localhost;port=5432;dbname=practicum4';

$pdo = new CountingPDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_STATEMENT_CLASS => [CountingStatement::class],
]);