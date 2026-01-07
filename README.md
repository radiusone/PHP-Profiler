# PHP Profiler #
Version 3

[Original code](https://github.com/steves/PHP-Profiler) by Steven Surowiec

## Introduction ##
A tool to log some basic debug info, for display in your app.

## Installation ##
`composer require --dev radiusone/php-profiler`

## Setup and Usage ##
The profiler needs to be instantiated before collecting data. After data collection is complete,
results can be displayed. For example:

```php
use PhpProfiler\Console;
use PhpProfiler\Profiler;

$profiler = new Profiler();
Console::logSpeed('Start Sample run');
Console::logMemory($object);
Console::logSpeed('End Sample run');
// Exceptions can also be logged:
try {
    // Some code goes here
} catch (Exception $e) {
    Console::logError($e);
}

// Database queries can be logged as well:
Console::logQuery($sql);  // Starts timer for query
$res = $db->execute($sql);
Console::logQuery($sql);  // Ends timer for query

// or manually
$start = microtime(true);
$res = $db->execute($sql);
$end = microtime(true);
Console::logQueryManually($sql, null, $start, $end);

$profiler->display();
```

You can use a custom callback to explain queries for console
```php
$profiler = new Profiler(['query_explain_callback' => fn ($sql) => MyClass::someMethod($sql)]);
Console::logQuery($sql); // Starts timer for query
$res = $db->execute($sql);
Console::logQuery($sql); // Ends timer for query
$profiler->display();

class My_Class {
    /**
     * @param string $sql query with 'EXPLAIN' already added
     * @return array{possible_keys: string, key: string, type: string, rows: string}[]|null
     */
    public static function someMethod(string $sql): array|null
    {
      $res = get_db()->execute($sql);
      return $res->fetchAll($res);
    }
}
```

## Configuration ##
PHP Profiler lets you pass in some configuration options to help allow it to suit your own needs.

- **query_explain_callback** is the callback used to explain SQL queries to get additional
information on them. It should accept a string (the query, with "EXPLAIN" prepended) and
return a list of arrays, with keys conforming to the results of an `EXPLAIN` query in MySQL.
- **query_profiler_callback** is the callback used to integrate an extended query profiler such 
as [MySQL's](https://dev.mysql.com/doc/refman/8.4/en/show-profile.html). It should accept a string
and return a list of arrays, with keys conforming to the results of a `SHOW PROFILE` query in MySQL.

## Features ##
Below are some of the features of PHP Profiler

- Log any string, array or object to the console
- Log all queries and find out how long they took to run, individually and total
- Learn which queries are being run more than once with duplicate query counting
- Allows integration with your DAL to explain executed queries
- Displays all included files
- Displays total memory usage of page load
- Log memory usage of any string, variable or object
- Log specific points in your script to see how long it takes to get to them
- See how many queries on a given page are inserts, updates, selects and deletes with query type counting
