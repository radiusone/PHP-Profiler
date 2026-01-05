<?php
/**
 * Port of PHP Quick Profiler by Ryan Campbell
 * Original URL: http://particletree.com/features/php-quick-profiler
 */
class Profiler_Display {
    /**
     * Outputs the HTML, CSS and JavaScript that builds the console display
     *
     * @param array{'logs'|'files'|'fileTotals'|'queries'|'queryTotals'|'memoryTotals'|'speedTotals': array<string,mixed>} $output
     */
    public static function display(array $output): void
    {
        $logCount = count($output['logs']['console']['messages']);
        $fileCount = count($output['files']);
        $memoryUsed = $output['memoryTotals']['used'];
        $queryCount = $output['queryTotals']['all'];
        $speedTotal = $output['speedTotals']['total'];

        require_once(__DIR__ . '/resources/profiler.inc');
    }
}
