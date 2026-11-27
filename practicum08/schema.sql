CREATE TABLE IF NOT EXISTS tasks (
    id       SERIAL PRIMARY KEY,
    title    VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    priority VARCHAR(50) NOT NULL,
    done     SMALLINT NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_tasks_priority ON tasks (priority);
