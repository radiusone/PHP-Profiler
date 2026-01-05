<?php
/**
 * Port of PHP Quick Profiler by Ryan Campbell
 * Original URL: http://particletree.com/features/php-quick-profiler
 */
class Profiler_Console {
    /**
     * Holds the logs used when the console is displayed.
     * @var array<string,array<string,mixed>>
     */
    private static array $logs = [
        'console' => ['messages' => []],
        'memory' => ['messages' => []],
        'errors' => ['messages' => []],
        'speed' => ['messages' => []],
        'benchmarks' => ['messages' => []],
        'queries' => ['messages' => []],
    ];

    /**
     * Logs a variable to the console
     * @param mixed $data The data to log to the console
     * @return void
     */
    public static function log(mixed $data): void {
        self::$logs['console']['messages'][] = ['data' => $data];
    }

    /**
     * Logs the memory usage of the provided variable, or entire script
     * @param object|null $object Optional variable to log the memory usage of
     * @param string $name Optional name used to group variables and scripts together
     * @return void
     */
    public static function logMemory(?object $object = null, string $name = 'PHP'): void
    {
        $memory = $object ? strlen(serialize($object)) : memory_get_usage();

        $log_item = [
            'data' => $memory,
            'name' => $name,
            'dataType' => gettype($object),
        ];

        self::$logs['memory']['messages'][] = $log_item;
    }

    /**
     * Logs an exception or error
     *
     * @param Exception $exception
     * @param string $message
     * @return void
     */
    public static function logError(Exception $exception, string $message): void
    {
        $log_item = [
            'data' => $message,
            'type' => 'error',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        self::$logs['console'][] = $log_item;
    }

    /**
     * Starts a timer, a second call to this method will end the timer and cause the
     * time to be recorded and displayed in the console.
     *
     * @param string $name
     * @return void
     */
    public static function logSpeed(string $name = 'Point in Time'): void
    {
        $log_item = ['data' => microtime(true), 'name' => $name];

        self::$logs['speed']['messages'][] = $log_item;
    }

    /**
     * Records how long a query took to run when the same query is passed in twice.
     *
     * @param string $sql
     * @param array{'possible_keys'|'key'|'type'|'rows': string}|null $explain
     * @return void
     */
    public static function logQuery(string $sql, array $explain = null): void
    {
        // We use a hash of the query for two reasons. One is because for large queries the
        // hash will be considerably smaller in memory. The second is to make a dump of the
        // logs more easily readable.
        $hash = md5($sql);

        // If this query is in the log we need to see if an end time has been set. If no
        // end time has been set then we assume this call is closing a previous one.
        if (count(self::$logs['queries']['messages'][$hash] ?? [])) {
            $query = array_pop(self::$logs['queries']['messages'][$hash]);
            if (!$query['end_time']) {
                $query['end_time'] = microtime(true);
                $query['explain'] = $explain;
            }
            self::$logs['queries']['messages'][$hash][] = $query;

            return;
        }

        self::$logs['queries']['messages'][$hash][] = [
            'start_time' => microtime(true),
            'end_time' => false,
            'explain' => false,
            'sql' => $sql,
        ];
    }

    /**
     * Records how long a query took to run when you already know the details.
     *
     * @param string $sql
     * @param array{'possible_keys'|'key'|'type'|'rows': string}|null $explain
     * @param int|float $start start timestamp
     * @param int|float $end end timestamp (end-start should give duration in seconds)
     * @return void
     */
    public static function logQueryManually(
        string $sql,
        array|null $explain = null,
        int|float $start = 0,
        int|float $end = 0
    ): void {
        $hash = md5($sql);

        self::$logs['queries']['messages'][$hash][] = [
            'start_time' => $start,
            'end_time' => $end,
            'explain' => $explain,
            'sql' => $sql,
        ];
    }

    /**
     * Records the time it takes for an action to occur
     *
     * @param string $name The name of the benchmark
     * @return void
     *
     */
    public static function logBenchmark(string $name): void {
        $key = 'benchmark_ ' . $name;

        if (isset(self::$logs['benchmarks']['messages'][$key])) {
            $benchKey = md5(microtime(true));

            self::$logs['benchmarks']['messages'][$benchKey] = self::$logs['benchmarks']['messages'][$key];
            self::$logs['benchmarks']['messages'][$benchKey]['end_time'] = microtime(true);

            unset(self::$logs['benchmarks']['messages'][$key]);
            return;
        }

        self::$logs['benchmarks']['messages'][$key] = [
            'start_time' => microtime(true),
            'end_time' => false,
            'name' => $name,
        ];
    }

    /**
     * Returns all log data
     * @return array<string,array<string,mixed>>
     */
    public static function getLogs(): array {
        return self::$logs;
    }
}
