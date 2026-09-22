<?php
require_once 'Task.php'; 
class RecurringTask extends Task {
    private string $repeatInterval; 

    public function __construct(string $title, string $dueDate, string $priority, string $repeatInterval, bool $done = false) {
        parent::__construct($title, $dueDate, $priority, $done);
        $this->repeatInterval = $repeatInterval;
    }
    public function getInfo(): string {
        return parent::getInfo() . " <br><small style='color: #d81b60;'>[Повторюється: {$this->repeatInterval}]</small>";
    }
}