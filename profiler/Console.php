<?php
/**
 * Port of PHP Quick Profiler by Ryan Campbell
 * Original URL: http://particletree.com/features/php-quick-profiler
 *
 * @phpstan-type Logs array{
 *     log: array{data: mixed}[],
 *     memory: array{data: int, name: string, dataType: string}[],
 *     error: array{data: string, file: string, line: int}[],
 *     speed: array{data: float, name: string}[],
 *     benchmark: array<string, array{start_time: float, end_time: float|null, name: string}>,
 *     queries: array<string, array{sql: string, start_time: float, end_time: float|null, explain: array{possible_keys: string, key: string, type: string, rows: string}|null}[]>
 * }
 */
class Profiler_Console {
    /**
     * Holds the logs used when the console is displayed.
     * @var Logs
     */
    private static array $logs = [
        'log' => [],
        'memory' => [],
        'error' => [],
        'speed' => [],
        'benchmark' => [],
        'queries' => [],
    ];

    /**
     * Logs a variable to the console
     *
     * @param mixed $data The data to log to the console
     * @return void
     */
    public static function log(mixed $data): void {
        self::$logs['log'][] = ['data' => $data];
    }

    /**
     * Logs the memory usage of the provided variable, or entire script
     *
     * @param object|null $object Optional variable to log the memory usage of
     * @param string $name Optional name used to group variables and scripts together
     * @return void
     */
    public static function logMemory(?object $object = null, string $name = 'PHP'): void
    {
        if (is_null($object)) {
            $memory = memory_get_usage();
        } else {
            $used = memory_get_usage();
            $temp = unserialize(serialize($object));
            $memory = memory_get_usage() - $used;
            unset($temp);
        }

        $log_item = [
            'data' => $memory,
            'name' => $name,
            'dataType' => gettype($object),
        ];

        self::$logs['memory'][] = $log_item;
    }

    /**
     * Logs an exception or error
     *
     * @param Exception $exception
     * @param string|null $message
     * @return void
     */
    public static function logError(Exception $exception, ?string $message = null): void
    {
        $log_item = [
            'data' => $message ?? $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        self::$logs['error'][] = $log_item;
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

        self::$logs['speed'][] = $log_item;
    }

    /**
     * Records how long a query took to run when the same query is passed in twice.
     *
     * @param string $sql
     * @param array{possible_keys: string, key: string, type: string, rows: string}|null $explain
     * @return void
     */
    public static function logQuery(string $sql, ?array $explain = null): void
    {
        // We use a hash of the query for two reasons. One is because for large queries the
        // hash will be considerably smaller in memory. The second is to make a dump of the
        // logs more easily readable.
        $hash = md5($sql);

        // If this query is in the log we need to see if an end time has been set. If no
        // end time has been set then we assume this call is closing a previous one.
        $entry = self::$logs['queries'][$hash] ?? [];
        if (count($entry)) {
            $query = array_pop($entry);
            if (!$query['end_time']) {
                $query['end_time'] = microtime(true);
                $query['explain'] = $explain;
            }
            self::$logs['queries'][$hash][] = $query;

            return;
        }

        self::$logs['queries'][$hash][] = [
            'start_time' => microtime(true),
            'end_time' => null,
            'explain' => null,
            'sql' => $sql,
        ];
    }

    /**
     * Records how long a query took to run when you already know the details.
     *
     * @param string $sql
     * @param array{possible_keys: string, key: string, type: string, rows: string}|null $explain
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

        self::$logs['queries'][$hash][] = [
            'start_time' => floatval($start),
            'end_time' => floatval($end),
            'explain' => $explain,
            'sql' => $sql,
        ];
    }

    /**
     * Records the time it takes for an action to occur
     *
     * @param string $name The name of the benchmark
     * @return void
     */
    public static function logBenchmark(string $name): void {
        $key = 'benchmark_ ' . $name;

        if (isset(self::$logs['benchmark'][$key])) {
            $benchKey = md5(strval(microtime(true)));

            self::$logs['benchmark'][$benchKey] = self::$logs['benchmark'][$key];
            self::$logs['benchmark'][$benchKey]['end_time'] = microtime(true);

            unset(self::$logs['benchmark'][$key]);
            return;
        }

        self::$logs['benchmark'][$key] = [
            'start_time' => microtime(true),
            'end_time' => null,
            'name' => $name,
        ];
    }

    /**
     * Returns all log data
     * @return Logs
     */
    public static function getLogs(): array {
        return self::$logs;
    }
}
