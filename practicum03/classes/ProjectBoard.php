<?php
require_once 'Task.php';

class ProjectBoard {
    private array $tasks = []; 
    public function addTask(Task $task): void {
        $this->tasks[] = $task;
    }
    public function pendingTasks(): array {
        return array_filter($this->tasks, function(Task $task) {
            return !$task->isDone();
        });
    }
    public function markDone(string $title): void {
        foreach ($this->tasks as $task) {
            if ($task->getTitle() === $title) {
                $task->markAsDone();
            }
        }
    }

    public function getAllTasks(): array {
        return $this->tasks;
    }
}