<?php
/**
 * Port of PHP Quick Profiler by Ryan Campbell
 * Original URL: http://particletree.com/features/php-quick-profiler
 */
class Profiler_Profiler {
    /**
     * Holds log data collected by Profiler_Console
     * @var array{'logs'|'files'|'fileTotals'|'queries'|'queryTotals'|'memoryTotals'|'speedTotals': array<string,mixed>}
     */
    public array $output = [];

    /**
     * Sets the configuration options for this object and sets the start time.
     *
     * @param array{
     *     query_explain_callback: callable(string):array{'possible_keys'|'key'|'type'|'rows': string}|null,
     *     query_profiler_callback: callable(string):array{'possible_keys'|'key'|'type'|'rows': string}|null,
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
     * @param callable $callback
     */
    public function setQueryExplainCallback(callable $callback): void
    {
        $this->config['query_explain_callback'] = $callback;
    }

    /**
     * Shortcut for setting the callback used to interact with the MySQL
     * query profiler.
     *
     * @param callable $callback
     */
    public function setQueryProfilerCallback(callable $callback): void
    {
        $this->config['query_profiler_callback'] = $callback;
    }

    /**
     * Collects and aggregates data recorded by Profiler_Console.
     */
    public function gatherConsoleData(): void
    {
        $logs = Profiler_Console::getLogs();


        foreach ($logs as $type => $data) {
            // Console data will already be properly formatted.
            if ($type === 'console') {
                continue;
            }

            // Ignore empty message lists
            if (!$data['count']) {
                continue;
            }

            foreach ($data['messages'] as $message) {
                $data = $message;

                switch ($type) {
                    case 'logs':
                        $data['type'] = 'log';
                        $data['data'] = print_r($message['data'], true);
                        break;
                    case 'memory':
                        $data['type'] = 'memory';
                        $data['data'] = $this->getReadableFileSize($data['data']);
                        break;
                    case 'speed':
                        $data['type'] = 'speed';
                        $data['data'] = $this->getReadableTime(($message['data'] - $this->startTime) * 1000);
                        break;
                    case 'benchmarks':
                        $data['type'] = 'benchmark';
                        $data['data'] = $this->getReadableTime($message['end_time'] - $message['start_time']);
                        break;
                }

                if (isset($data['type'])) {
                    $logs['console']['messages'][] = $data;
                }
            }
        }

        $this->output['logs'] = $logs;
    }

    /**
     * Gathers and aggregates data on included files such as size
     */
    public function gatherFileData(): void
    {
        $files = get_included_files();
        $fileList = [];
        $fileTotals = ['count' => count($files), 'size' => 0, 'largest' => 0];

        foreach($files as $file) {
            $size = filesize($file);
            $fileList[] = ['name' => $file, 'size' => $this->getReadableFileSize($size)];
            $fileTotals['size'] += $size;
            $fileTotals['largest'] = max($size, $fileTotals['largest']);
        }

        $fileTotals['size'] = $this->getReadableFileSize($fileTotals['size']);
        $fileTotals['largest'] = $this->getReadableFileSize($fileTotals['largest']);

        $this->output['files'] = $fileList;
        $this->output['fileTotals'] = $fileTotals;
    }

    /**
     * Gets the peak memory usage the configured memory limit
     */
    public function gatherMemoryData(): void
    {
        $memoryTotals = [];
        $memoryTotals['used'] = $this->getReadableFileSize(memory_get_peak_usage());
        $memoryTotals['total'] = ini_get('memory_limit');

        $this->output['memoryTotals'] = $memoryTotals;
    }

    /**
     * Gathers and aggregates data regarding executed queries
     */
    public function gatherQueryData(): void
    {
        $queries = [];
        $type_default = ['total' => 0, 'time' => 0, 'percentage' => 0, 'time_percentage' => 0];
        $types = ['select' => $type_default, 'update' => $type_default, 'insert' => $type_default, 'delete' => $type_default];
        $queryTotals = ['all' => 0, 'count' => 0, 'time' => 0, 'duplicates' => 0, 'types' => $types];
        $queryTypes = ['select', 'update', 'delete', 'insert'];

        foreach($this->output['logs']['queries']['messages'] as $entries) {
            if (count($entries) > 1) {
                $queryTotals['duplicates'] += 1;
            }

            $queryTotals['count'] += 1;
            foreach ($entries as $i => $log) {
                if (isset($log['end_time'])) {
                    $query = [
                        'sql' => $log['sql'],
                        'explain' => $log['explain'],
                        'time' => $log['end_time'] - $log['start_time'],
                        'duplicate' => $i > 0,
                    ];

                    // Lets figure out the type of query for our counts
                    $trimmed = trim($log['sql']);
                    $type = strtolower(substr($trimmed, 0, strpos($trimmed, ' ')));

                    if (in_array($type, $queryTypes) && isset($queryTotals['types'][$type])) {
                        $queryTotals['types'][$type]['total'] += 1;
                        $queryTotals['types'][$type]['time'] += $query['time'];
                    }

                    // Need to get total times and a readable format of our query time
                    $queryTotals['time'] += $query['time'];
                    $queryTotals['all'] += 1;
                    $query['time'] = $this->getReadableTime($query['time']);

                    // If an explain callback is setup try to get the explain data
                    if (isset($queryTypes[$type]) && !empty($this->config['query_explain_callback'])) {
                        $query['explain'] = $this->attemptToExplainQuery($query['sql']);
                    }

                    // If a query profiler callback is setup get the profiler data
                    if (!empty($this->config['query_profiler_callback'])) {
                        $query['profile'] = $this->attemptToProfileQuery($query['sql']);
                    }

                    $queries[] = $query;
                }
            }
        }

        // Go through the type totals and calculate percentages
        foreach ($queryTotals['types'] as $type => $stats) {
            $total_perc = !$stats['total'] ? 0 : round(($stats['total'] / $queryTotals['count']) * 100, 2);
            $time_perc = !$stats['time'] ? 0 : round(($stats['time'] / $queryTotals['time']) * 100, 2);

            $queryTotals['types'][$type]['percentage'] = $total_perc;
            $queryTotals['types'][$type]['time_percentage'] = $time_perc;
            $queryTotals['types'][$type]['time'] = $this->getReadableTime($queryTotals['types'][$type]['time']);
        }

        $queryTotals['time'] = $this->getReadableTime($queryTotals['time']);
        $this->output['queries'] = $queries;
        $this->output['queryTotals'] = $queryTotals;
    }

    /**
     * Calculates the execution time from the start of profiling to *now* and
     * collects the congirued maximum execution time.
     */
    public function gatherSpeedData(): void
    {
        $speedTotals = [];
        $speedTotals['total'] = $this->getReadableTime((microtime(true) - $this->startTime)*1000);
        $speedTotals['allowed'] = ini_get('max_execution_time');
        $this->output['speedTotals'] = $speedTotals;
    }

    /**
     * Converts a number of bytes to a more readable format
     * @param int $size The number of bytes
     * @param string|null $retString The format of the return string
     * @return string
     */
    public function getReadableFileSize(int $size, string|null $retString = null): string
    {
        $sizes = ['bytes', 'kB', 'MB', 'GB', 'TB'];

        $retString ??= '%01.2f %s';

        $sizeString = $sizes[0];
        $lastSizeString = end($sizes);

        if ($size < 1024) {
            $retString = '%01d %s';
        } else {
            foreach ($sizes as $sizeString) {
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
    public function getReadableTime(int|float $time): string
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
     * Collects data from the console and performs various calculations on it before
     * displaying the console on screen.
     */
    public function display(): void
    {
        $this->gatherConsoleData();
        $this->gatherFileData();
        $this->gatherMemoryData();
        $this->gatherQueryData();
        $this->gatherSpeedData();

        Profiler_Display::display($this->output, $this->config);
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