<?php
/**
 * Port of PHP Quick Profiler by Ryan Campbell
 * Original URL: http://particletree.com/features/php-quick-profiler
 */
class Profiler_Console {
    /**
     * Holds the logs used when the console is displayed.
     * @var array
     */
    private static $_logs = [
        'console' => ['messages' => [], 'count' => 0],
        'memory' => ['messages' => [], 'count' => 0],
        'errors' => ['messages' => [], 'count' => 0],
        'speed' => ['messages' => [], 'count' => 0],
        'benchmarks' => ['messages' => [], 'count' => 0],
        'queries' => ['messages' => [], 'count' => 0],
    ];

    /**
     * Logs a variable to the console
     * @param mixed $data The data to log to the console
     * @return void
     */
    public static function log($data) {
        self::$_logs['console']['messages'][] = ['data' => $data];
        self::$_logs['console']['count'] += 1;
    }

    /**
     * Logs the memory usage of the provided variable, or entire script
     * @param object $object Optional variable to log the memory usage of
     * @param string $name Optional name used to group variables and scripts together
     * @return void
     */
    public static function logMemory($object = false, $name = 'PHP') {
        $memory = $object ? strlen(serialize($object)) : memory_get_usage();

        $log_item = [
            'data' => $memory,
            'name' => $name,
            'dataType' => gettype($object),
        ];

        self::$_logs['memory']['messages'][] = $log_item;
        self::$_logs['memory']['count'] += 1;
    }

    /**
     * Logs an exception or error
     * @param Exception $exception
     * @param string $message
     * @return void
     */
    public static function logError($exception, $message) {
        $log_item = [
            'data' => $message,
            'type' => 'error',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        self::$debugger_logs['console'][] = $log_item;
        self::$debugger_logs['errorCount'] += 1;
    }

    /**
     * Starts a timer, a second call to this method will end the timer and cause the
     * time to be recorded and displayed in the console.
     * @param string $name
     * @return void
     */
    public static function logSpeed($name = 'Point in Time') {
        $log_item = ['data' => microtime(true), 'name' => $name];

        self::$_logs['speed']['messages'][] = $log_item;
        self::$_logs['speed']['count'] += 1;
    }

    /**
     * Records how long a query took to run when the same query is passed in twice.
     * @param string $sql
     * @return void
     */
    public static function logQuery($sql, $explain = null) {
        // We use a hash of the query for two reasons. One is because for large queries the
        // hash will be considerably smaller in memory. The second is to make a dump of the
        // logs more easily readable.
        $hash = md5($sql);

        // If this query is in the log we need to see if an end time has been set. If no
        // end time has been set then we assume this call is closing a previous one.
        if (isset(self::$_logs['queries']['messages'][$hash])) {
            $query = array_pop(self::$_logs['queries']['messages'][$hash]);
            if (!$query['end_time']) {
                $query['end_time'] = microtime(true);
                $query['explain'] = $explain;

                self::$_logs['queries']['messages'][$hash][] = $query;
            } else {
                self::$_logs['queries']['messages'][$hash][] = $query;
            }

            self::$_logs['queries']['count'] += 1;
            return;
        }

        $log_item = [
            'start_time' => microtime(true),
            'end_time' => false,
            'explain' => false,
            'sql' => $sql,
        ];

        self::$_logs['queries']['messages'][$hash][] = $log_item;
    }

    /**
     * Records how long a query took to run when you already know the details.
     * @param string $sql
     * @param string $start - should be 0
     * @param string $end - microtime of duration query took
     * @return void
     */
    public static function logQueryManually($sql, $explain = null, $start, $end) {
        // We use a hash of the query for two reasons. One is because for large queries the
        // hash will be considerably smaller in memory. The second is to make a dump of the
        // logs more easily readable.
        $hash = md5($sql);

        $log_item = [
            'start_time' => $start,
            'end_time' => $end,
            'explain' => false,
            'sql' => $sql,
        ];

        self::$_logs['queries']['messages'][$hash][] = $log_item;
    }

    /**
     * Records the time it takes for an action to occur
     *
     * @param string $name The name of the benchmark
     * @return void
     *
     */
    public static function logBenchmark($name) {
        $key = 'benchmark_ ' . $name;

        if (isset(self::$_logs['benchmarks']['messages'][$key])) {
            $benchKey = md5(microtime(true));

            self::$_logs['benchmarks']['messages'][$benchKey] = self::$_logs['benchmarks']['messages'][$key];
            self::$_logs['benchmarks']['messages'][$benchKey]['end_time'] = microtime(true);
            self::$_logs['benchmarks']['count'] += 1;

            unset(self::$_logs['benchmarks']['messages'][$key]);
            return;
        }

        $log_item = [
            'start_time' => microtime(true),
            'end_time' => false,
            'name' => $name,
        ];

        self::$_logs['benchmarks']['messages'][$key] = $log_item;
    }

    /**
     * Returns all log data
     * @return array
     */
    public static function getLogs() {
        return self::$_logs;
    }
}