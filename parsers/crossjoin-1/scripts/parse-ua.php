#!/usr/bin/env php
<?php

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

$uaPos       = array_search('--ua', $argv);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[2];
}

$result    = null;
$parseTime = 0;

$start = microtime(true);
require __DIR__ . '/../vendor/autoload.php';
$cacheDir = __DIR__ . '/../data';
\Crossjoin\Browscap\Cache\File::setCacheDirectory($cacheDir);
\Crossjoin\Browscap\Browscap::setDatasetType(\Crossjoin\Browscap\Browscap::DATASET_TYPE_LARGE);
$updater = new \Crossjoin\Browscap\Updater\None();
\Crossjoin\Browscap\Browscap::setUpdater($updater);
$browscap = new \Crossjoin\Browscap\Browscap();
$browscap->getBrowser('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $browscap->getBrowser($agentString)->getData();
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
                'name'     => $r->device_name,
                'brand'    => $r->device_maker,
                'type'     => $r->device_type,
                'ismobile' => $r->ismobiledevice,
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('crossjoin/browscap');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion() . '-' . \Crossjoin\Browscap\Browscap::getParser()->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
