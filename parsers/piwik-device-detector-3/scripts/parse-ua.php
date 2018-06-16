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
require_once __DIR__ . '/vendor/autoload.php';
use DeviceDetector\DeviceDetector;

$dd = new DeviceDetector('Test String');
$dd->skipBotDetection();
$dd->parse();
$initTime = microtime(true) - $start;

if ($hasUa) {
    $dd->setUserAgent($agentString);

    $start = microtime(true);
    $dd->parse();
    $end = microtime(true) - $start;

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $clientInfo['name'] ?? '',
                'version' => $clientInfo['version'] ?? '',
            ],
            'platform' => [
                'name'    => $osInfo['name'] ?? '',
                'version' => $osInfo['version'] ?? '',
            ],
            'device' => [
                'name'     => null !== $model ? $model : '',
                'brand'    => null !== $brand ? $brand : '',
                'type'     => null !== $device ? $device : '',
                'ismobile' => $isMobile ? true : false,
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('piwik/device-detector');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
