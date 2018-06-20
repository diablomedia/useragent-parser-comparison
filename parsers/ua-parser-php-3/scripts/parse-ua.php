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
$parser = UAParser\Parser::create();
$parser->parse('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $parser->parse($agentString);
    $end   = microtime(true) - $start;

    $browserVersion  = $r->ua->toVersion();
    $platformVersion = $r->ua->toVersion();

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $r->ua->family,
                'version' => $browserVersion,
            ],
            'platform' => [
                'name'    => $r->os->family,
                'version' => $platformVersion,
            ],
            'device' => [
                'name'     => $r->device->model === null ? '' : $r->device->model,
                'brand'    => $r->device->brand === null ? '' : $r->device->brand,
                'type'     => null,
                'ismobile' => null,
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('ua-parser/uap-php');

$regexVersion = file_get_contents(__DIR__ . '/../version.txt');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion() . '-' . $regexVersion,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
