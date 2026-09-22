<?php
class Task {
    protected string $title;
    protected string $dueDate;
    protected string $priority;
    protected bool $done;

    public function __construct(string $title, string $dueDate, string $priority, bool $done = false) {
        $this->title = $title;
        $this->dueDate = $dueDate;
        $this->priority = $priority;
        $this->done = $done;
    }

    public function getInfo(): string {
        $status = $this->done ? 'Виконано' : 'В роботі';
        return "<strong>{$this->title}</strong> (Дедлайн: {$this->dueDate}, Пріоритет: {$this->priority}) — Статус: <em>{$status}</em>";
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function isDone(): bool {
        return $this->done;
    }

    public function markAsDone(): void {
        $this->done = true;
    }
    
    public function getDueDate(): string {
        return $this->dueDate;
    }
}