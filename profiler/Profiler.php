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
     *     messages: array{
     *          log: array,
     *          memory: array,
     *          error: array,
     *          speed: array,
     *          benchmark: array,
     *          all: array
     *     },
     *     files: array{name: string, bytes: int, size: string},
     *     fileTotals: array{size: string, largest: string},
     *     queries: array<string,mixed>,
     *     queryTotals: array{
     *          total: int,
     *          duplicates: int,
     *          time: string,
     *          'select'|'insert'|'update'|'delete': array{
     *              total: int,
     *              time: string,
     *              percentage: string,
     *              time_percentage: string
     *          }
     *     },
     *     memoryTotals: array{used: string, total: string},
     *     speedTotals: array{total: string, allowed: string}
     * }
     */
    public array $output = [];

    /**
     * Sets the configuration options for this object and sets the start time.
     *
     * @param array{
     *     query_explain_callback: callable(string):(array{'possible_keys'|'key'|'type'|'rows': string}|null),
     *     query_profiler_callback: callable(string):(array{status: string, duration: float}[]|null),
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
     * @param callable(string):(array{'possible_keys'|'key'|'type'|'rows': string}|null) $callback
     */
    public function setQueryExplainCallback(callable $callback): void
    {
        $this->config['query_explain_callback'] = $callback;
    }

    /**
     * Shortcut for setting the callback used to interact with the MySQL
     * query profiler.
     *
     * @param callable(string):(array{status: string, duration: float}[]|null) $callback
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

        foreach ($logs as $type => $data) {
            foreach ($data['messages'] as $message) {
                $message['type'] = $type;
                switch ($type) {
                    case 'log':
                        $message['type'] = 'log';
                        if (!is_scalar($message['data'])) {
                            $message['data'] = json_encode($message['data'], JSON_PRETTY_PRINT) ?: '';
                        } else {
                            $message['data'] = strval($message['data']);
                        }
                        break;
                    case 'memory':
                        $message['data'] = self::getReadableFileSize($message['data']);
                        break;
                    case 'speed':
                        $message['data'] = self::getReadableTime(($message['data'] - $this->startTime) * 1000);
                        break;
                    case 'benchmark':
                        $message['data'] = self::getReadableTime($message['end_time'] - $message['start_time']);
                        break;
                    case 'error':
                        break;
                    default:
                        continue(2);
                }

                $this->output['messages'][$type][] =
                $this->output['messages']['all'][] = $message;
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

        foreach($logs['queries']['messages'] as $entries) {
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
            $tq = array_filter($queries, fn ($v) => str_starts_with(strtolower($v), $type));
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
     * @return array
     */
    protected function attemptToExplainQuery(string $sql): array
    {
        if (empty($this->config['query_explain_callback'])) {
            return [];
        }
        $sql = 'EXPLAIN ' . $sql;

        return call_user_func($this->config['query_explain_callback'], $sql);
    }

    /**
     * Used with a callback to allow integration into DAL's to profiler an execute query.
     *
     * @param string $sql The query being profiled
     * @return array
     */
    protected function attemptToProfileQuery(string $sql): array
    {
        if (empty($this->config['query_profiler_callback'])) {
            return [];
        }

        return call_user_func($this->config['query_profiler_callback'], $sql);
    }
}