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
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Device;
use Sinergi\BrowserDetector\Os;

new Browser('Test String');
new Os('Test String');
new Device('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start   = microtime(true);
    $browser = new Browser($agentString);
    $os      = new Os($agentString);
    $device  = new Device($agentString);
    $end     = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $browser->getName(),
                'version' => $browser->getVersion(),
            ],
            'platform' => [
                'name'    => $os->getName(),
                'version' => $os->getVersion(),
            ],
            'device' => [
                'name'     => $device->getName(),
                'brand'    => null,
                'type'     => null,
                'ismobile' => $os->isMobile(),
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('sinergi/browser-detector');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
