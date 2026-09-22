<?php
function formatDateUA(string $dateString): string {
    $date = date_create($dateString);
    return date_format($date, 'd.m.Y');
}
function isOverdue(string $dueDate): bool {
    return $dueDate < date('Y-m-d');
}