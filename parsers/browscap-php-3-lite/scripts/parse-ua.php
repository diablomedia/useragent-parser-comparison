#!/usr/bin/env php
<?php

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

$uaPos = array_search('--ua', $argv);
$hasUa    = false;
$agentString = '';

if (false !== $uaPos) {
    $hasUa = true;

    $agentString = $argv[1];
}

$result    = null;
$parseTime = 0;

$start = microtime(true);
require __DIR__ . '/vendor/autoload.php';
$cacheDir = __DIR__ . '/data';
$bc       = new \BrowscapPHP\Browscap();
$adapter  = new \WurflCache\Adapter\File([\WurflCache\Adapter\File::DIR => $cacheDir]);
$bc->setCache($adapter);
$bc->getBrowser('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $bc->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $result = [
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
                'name'     => null,
                'brand'    => null,
                'type'     => $r->device_type,
                'ismobile' => $r->ismobiledevice ? true : false,
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('browscap/browscap-php');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion() . '-' . $bc->getCache()->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
