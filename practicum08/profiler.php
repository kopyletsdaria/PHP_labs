<?php
// Профілювання - час виконання, пікова пам'ять і лічильник SQL-запитів

class Profiler
{
    public static float $start = 0.0;
    public static int $queries = 0;
    public static string $cache = 'none'; 

    public static function start(): void
    {
        self::$start = microtime(true);
        self::$queries = 0;
    }

    public static function elapsedMs(): float
    {
        return (microtime(true) - self::$start) * 1000;
    }

    public static function peakMemoryMb(): float
    {
        return memory_get_peak_usage(true) / 1024 / 1024;
    }
}

class CountingStatement extends PDOStatement
{
    public function execute(?array $params = null): bool
    {
        Profiler::$queries++;
        return parent::execute($params);
    }
}

class CountingPDO extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        Profiler::$queries++;
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
