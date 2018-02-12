#!/usr/bin/env php
<?php

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

$benchmarkPos = array_search('--benchmark', $argv);
$benchmark    = false;

if (false !== $benchmarkPos) {
    $benchmark = true;
    unset($argv[$benchmarkPos]);
    $argv = array_values($argv);
}

$agentListFile = $argv[1];

$results   = [];
$parseTime = 0;

$start = microtime(true);
get_browser('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (!empty($agentString)) {
        $start = microtime(true);
        $r     = get_browser($agentString);
        $end   = microtime(true) - $start;

        if (!$benchmark) {
            $results[] = [
                'useragent' => $agentString,
                'parsed'    => [
                    'browser' => [
                        'name'    => $r->browser,
                        'version' => $r->version,
                    ],
                    'platform' => [
                        'name'    => $r->platform,
                        'version' => $r->platform_version,
                    ],
                    'device' => [
                        'name'     => $r->device_name,
                        'brand'    => $r->device_maker,
                        'type'     => $r->device_type,
                        'ismobile' => $r->ismobiledevice ? true : false,
                    ],
                ],
                'time' => $end,
            ];
        }

        $parseTime += $end;
    }
}

$file = null;

$memory = memory_get_peak_usage();

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => PHP_VERSION . '-' . file_get_contents(__DIR__ . '/../data/version.txt'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
