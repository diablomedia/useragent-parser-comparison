<?php

declare(strict_types = 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$benchmarkPos = array_search('--benchmark', $argv);
$benchmark    = false;

if ($benchmarkPos !== false) {
    $benchmark = true;
    unset($argv[$benchmarkPos]);
    $argv = array_values($argv);
}

$agentListFile = $argv[1];

$results   = [];
$parseTime = 0;

$start = microtime(true);
require_once __DIR__ . '/../vendor/autoload.php';

use EndorphinStudio\Detector\Detector;

$detector = new Detector();

$detector->analyse('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = $detector->analyse($agentString);
    $end   = microtime(true) - $start;

    $r = json_decode(json_encode($r));

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $r->isRobot ? (isset($r->robot) ? $r->robot->name : null) : (isset($r->browser) ? $r->browser->name : null),
                'version' => isset($r->browser) ? $r->browser->version : null,
            ],
            'platform' => [
                'name'    => isset($r->os) ? $r->os->name : null,
                'version' => isset($r->os) ? $r->os->version : null,
            ],
            'device' => [
                'name'     => isset($r->device) ? $r->device->name : null,
                'brand'    => null,
                'type'     => isset($r->device) ? $r->device->type : null,
                'ismobile' => $r->isMobile ? true : false,
            ],
        ],
        'time' => $end,
    ];
}

$file   = null;
$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('endorphin-studio/browser-detector');

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
