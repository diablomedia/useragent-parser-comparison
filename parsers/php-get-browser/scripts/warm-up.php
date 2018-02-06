#!/usr/bin/env php
<?php

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

$start = microtime(true);
get_browser('Test String');
$initTime = microtime(true) - $start;

$memory = memory_get_peak_usage();

echo json_encode([
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => PHP_VERSION . '-' . file_get_contents(__DIR__ . '/../data/version.txt'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
