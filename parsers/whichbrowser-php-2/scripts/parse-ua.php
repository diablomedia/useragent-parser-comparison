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
$result   = new WhichBrowser\Parser('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = new WhichBrowser\Parser($agentString);
    $end   = microtime(true) - $start;

    $isMobile = $r->isMobile();

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => !empty($r->browser->name) ? $r->browser->name : '',
                'version' => !empty($r->browser->version) ? $r->browser->version->value : '',
            ],
            'platform' => [
                'name'    => !empty($r->os->name) ? $r->os->name : '',
                'version' => !empty($r->os->version->value) ? $r->os->version->value : '',
            ],
            'device' => [
                'name'     => !empty($r->device->model) ? $r->device->model : '',
                'brand'    => !empty($r->device->manufacturer) ? $r->device->manufacturer : '',
                'type'     => !empty($r->device->type) ? $r->device->type : '',
                'ismobile' => $isMobile ? 'true' : 'false',
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('whichbrowser/parser');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
