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
$cacheDir               = __DIR__ . '/data';
$browscap               = new phpbrowscap\Browscap($cacheDir);
$browscap->doAutoUpdate = false;
// Initialize and include in init, but don't want to blow out the time on a real agent
// if cache loading and all that has to happen here
$browscap->getBrowser('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $browscap->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $r->Browser,
                'version' => $r->Version,
            ],
            'platform' => [
                'name'    => $r->Platform,
                'version' => $r->Platform_Version,
            ],
            'device' => [
                'name'     => $r->Device_Name,
                'brand'    => $r->Device_Maker,
                'type'     => $r->Device_Type,
                'ismobile' => $r->isMobileDevice ? true : false,
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
    'version'     => $package->getVersion() . '-' . $browscap->getSourceVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
