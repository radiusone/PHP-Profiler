<?php
/**
 * Port of PHP Quick Profiler by Ryan Campbell
 * Original URL: http://particletree.com/features/php-quick-profiler
 *
 * @phpstan-import-type Logs from Profiler_Console
 */
class Profiler_Profiler {
    /**
     * Holds log data collected by Profiler_Console
     *
     * @var array{
     *     messages?: array{
     *         log: array{data: string, type: 'log', ...}[]|array{},
     *         memory: array{data: string, dataType: string, name: string, type: 'memory', ...}[]|array{},
     *         error: array{data: string, file: string, line: int, type: 'error', ...}[]|array{},
     *         speed: array{data: string, name: string, type: 'speed', ...}[]|array{},
     *         benchmark: array{data: string, name: string, type: 'benchmark', ...}[]|array{},
     *         all: list<array{data: string, type: 'log'}|array{data: string, dataType: string, name: string, type: 'memory'}|array{data: string, file: string, line: int, type: 'error'}|array{data: string, name: string, type: 'speed'}|array{data: string, name: string, type: 'benchmark'}>|array{},
     *     },
     *     files?: array{name: string, bytes: int, size: string}[],
     *     fileTotals?: array{size: string, largest: string},
     *     queries?: array{
     *         sql: string,
     *         time: string,
     *         duplicate: bool,
     *         explain: array{possible_keys: string, key: string, type: string, rows: string}|null,
     *         profile: array{Status: string, Duration: string}[]|null
     *      }[],
     *     queryTotals?: array{
     *         duplicates: int,
     *         time: string,
     *         select: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
     *         insert: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
     *         update: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
     *         delete: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
     *     },
     *     memoryTotals?: array{used: string, total: string},
     *     speedTotals?: array{total: string, allowed: string}
     * }
     */
    public array $output = [];

    /**
     * Sets the configuration options for this object and sets the start time.
     *
     * @param array{
     *     query_explain_callback?: callable(string):(array{possible_keys: string, key: string, type: string, rows: string}|null),
     *     query_profiler_callback?: callable(string):(array{Status: string, Duration: float}[]|null),
     * } $config List of configuration options
     * @param int|float|null $startTime Time to use as the start time of the profiler
     */
    public function __construct(
        public array $config = [],
        public int|float|null $startTime = null
    )
    {
        $this->startTime ??= microtime(true);
    }

    /**
     * Shortcut for setting the callback used to explain queries.
     *
     * @param callable(string):(array{possible_keys: string, key: string, type: string, rows: string}|null) $callback
     */
    public function setQueryExplainCallback(callable $callback): void
    {
        $this->config['query_explain_callback'] = $callback;
    }

    /**
     * Shortcut for setting the callback used to interact with the MySQL
     * query profiler.
     *
     * @param callable(string):(array{Status: string, Duration: float}[]|null) $callback
     */
    public function setQueryProfilerCallback(callable $callback): void
    {
        $this->config['query_profiler_callback'] = $callback;
    }

    /**
     * Collects and aggregates data recorded by Profiler_Console.
     */
    protected function gatherConsoleData(): void
    {
        /** @var Logs $logs */
        $logs = Profiler_Console::getLogs();
        $this->output['messages'] = [
            'log' => [],
            'memory' => [],
            'speed' => [],
            'benchmark' => [],
            'error' => [],
            'all' => [],
        ];

        foreach ($logs as $type => $data) {
            switch ($type) {
                case 'log':
                    foreach ($data as $message) {
                        $message['type'] = $type;
                        if (!is_scalar($message['data'])) {
                            $message['data'] = json_encode($message['data'], JSON_PRETTY_PRINT) ?: '';
                        } else {
                            $message['data'] = strval($message['data']);
                        }
                        $this->output['messages'][$type][] = $message;
                    }
                    $this->output['messages']['all'] = array_merge($this->output['messages']['all'], $this->output['messages'][$type]);
                    break;
                case 'memory':
                    foreach ($data as $message) {
                        $message['type'] = $type;
                        $message['data'] = self::getReadableFileSize($message['data']);
                        $this->output['messages'][$type][] = $message;
                    }
                    $this->output['messages']['all'] = array_merge($this->output['messages']['all'], $this->output['messages'][$type]);
                    break;
                case 'speed':
                    foreach ($data as $message) {
                        $message['type'] = $type;
                        $message['data'] = self::getReadableTime(($message['data'] - $this->startTime) * 1000);
                        $this->output['messages'][$type][] = $message;
                    }
                    $this->output['messages']['all'] = array_merge($this->output['messages']['all'], $this->output['messages'][$type]);
                    break;
                case 'benchmark':
                    foreach ($data as $message) {
                        $message['type'] = $type;
                        $message['data'] = self::getReadableTime($message['end_time'] - $message['start_time']);
                        $this->output['messages'][$type][] = $message;
                    }
                    $this->output['messages']['all'] = array_merge($this->output['messages']['all'], $this->output['messages'][$type]);
                    break;
                case 'error':
                    foreach ($data as $message) {
                        $message['type'] = $type;
                        $this->output['messages'][$type][] = $message;
                    }
                    $this->output['messages']['all'] = array_merge($this->output['messages']['all'], $this->output['messages'][$type]);
                    break;
                }
        }

    }

    /**
     * Gathers and aggregates data on included files such as size
     */
    protected function gatherFileData(): void
    {
        $fileList = array_map(function($v) {
            return [
                'name' => $v,
                'bytes' => $bytes = filesize($v) ?: 0,
                'size' => self::getReadableFileSize($bytes),
            ];
        }, get_included_files());

        $this->output['files'] = $fileList;
        $bytes = array_column($fileList, 'bytes');
        $this->output['fileTotals'] = [
            'size' => self::getReadableFileSize(array_sum($bytes)),
            'largest' => self::getReadableFileSize(max($bytes)),
        ];
    }

    /**
     * Gets the peak memory usage the configured memory limit
     */
    protected function gatherMemoryData(): void
    {
        $this->output['memoryTotals'] = [
            'used' => self::getReadableFileSize(memory_get_peak_usage()),
            'total' => ini_get('memory_limit'),
        ];
    }

    /**
     * Gathers and aggregates data regarding executed queries
     */
    protected function gatherQueryData(): void
    {
        /** @var Logs $logs */
        $logs = Profiler_Console::getLogs();
        $queries = [];
        $queryTotals = ['all' => 0, 'duplicates' => 0];
        $queryTypes = ['select', 'update', 'delete', 'insert'];

        foreach($logs['queries'] as $entries) {
            if (count($entries) > 1) {
                $queryTotals['duplicates'] += 1;
            }

            foreach ($entries as $i => $log) {
                if (!isset($log['end_time'])) {
                    continue;
                }
                $query = [
                    'sql' => $log['sql'],
                    'explain' => $log['explain'],
                    'time' => self::getReadableTime($log['end_time'] - $log['start_time']),
                    'duplicate' => $i > 0,
                    'profile' => null,
                ];

                // If an explain callback is setup try to get the explain data

                $type = preg_match('/^ *(' . implode('|', $queryTypes) . ') /i', $log['sql']);
                if ($type && !empty($this->config['query_explain_callback'])) {
                    $query['explain'] ??= $this->attemptToExplainQuery($query['sql']);
                }

                // If a query profiler callback is setup get the profiler data
                if (!empty($this->config['query_profiler_callback'])) {
                    $query['profile'] = $this->attemptToProfileQuery($query['sql']);
                }

                $queries[] = $query;
            }
        }

        $queryTotals['time'] = array_sum(array_column($queries, 'time'));
        foreach ($queryTypes as $type) {
            $tq = array_filter($queries, fn ($v) => str_starts_with(strtolower($v['sql']), $type));
            $tq_time = array_sum(array_column($tq, 'time'));
            $queryTotals[$type] = [
                'total' => count($tq),
                'time' => self::getReadableTime($tq_time),
                'percentage' => round(count($tq) / count($queries) * 100, 2),
                'time_percentage' => round($tq_time / $queryTotals['time'] * 100, 2),
            ];
        }
        $queryTotals['time'] = self::getReadableTime($queryTotals['time']);
        $this->output['queries'] = $queries;
        $this->output['queryTotals'] = $queryTotals;
    }

    /**
     * Calculates the execution time from the start of profiling to *now* and
     * collects the congirued maximum execution time.
     */
    protected function gatherSpeedData(): void
    {
        $this->output['speedTotals'] = [
            'total' => self::getReadableTime((microtime(true) - $this->startTime)*1000),
            'allowed' => ini_get('max_execution_time'),
        ];
    }

    /**
     * Converts a number of bytes to a more readable format
     * @param int $size The number of bytes
     * @param string|null $retString The format of the return string
     * @return string
     */
    public static function getReadableFileSize(int $size, string|null $retString = null): string
    {
        $sizes = ['bytes', 'kB', 'MB', 'GB', 'TB'];

        $retString ??= '%01.2f %s';

        $sizeString = $sizes[0];
        $lastSizeString = end($sizes);

        if ($size < 1024) {
            $retString = '%d %s';
        } else {
            foreach ($sizes as $sizeString) {
                if ($size < 1024) {
                    break;
                }
                if ($sizeString != $lastSizeString) {
                    $size /= 1024;
                }
            }
        }

        return sprintf($retString, $size, $sizeString);
    }

    /**
     * Converts a small time format (fractions of a millisecond) to a more readable format
     * @param int|float $time
     * @return string
     */
    public static function getReadableTime(int|float $time): string
    {
        if ($time >= 1000 && $time < 60000) {
            $unit = 's';
            $ret = ($time / 1000);
        } elseif ($time >= 60000) {
            $unit = 'm';
            $ret = ($time / 1000) / 60;
        } else {
            $ret = $time;
            $unit = 'ms';
        }

        return number_format($ret, 3, '.', '') . ' ' . $unit;
    }

    /**
     * Populate the output property with data from the console and format it for output
     *
     * @return void
     */
    public function prepareOutput(): void
    {
        $this->gatherConsoleData();
        $this->gatherFileData();
        $this->gatherMemoryData();
        $this->gatherQueryData();
        $this->gatherSpeedData();
    }

    /**
     * Display the console on screen.
     */
    public function display(): void
    {
        $this->prepareOutput();
        $output = $this->output;

        require_once(__DIR__ . '/resources/profiler.inc');
    }

    /**
     * Used with a callback to allow integration into DAL's to explain an executed query.
     *
     * @param string $sql The query that is being explained
     * @return array{possible_keys: string, key: string, type: string, rows: string}|null
     */
    protected function attemptToExplainQuery(string $sql): ?array
    {
        if (empty($this->config['query_explain_callback'])) {
            return null;
        }
        $sql = 'EXPLAIN ' . $sql;

        return call_user_func($this->config['query_explain_callback'], $sql);
    }

    /**
     * Used with a callback to allow integration into DAL's to profiler an execute query.
     *
     * @param string $sql The query being profiled
     * @return array{Status: string, Duration: float}[]|null
     */
    protected function attemptToProfileQuery(string $sql): ?array
    {
        if (empty($this->config['query_profiler_callback'])) {
            return null;
        }

        return call_user_func($this->config['query_profiler_callback'], $sql);
    }
}