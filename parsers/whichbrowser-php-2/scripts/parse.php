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
require __DIR__ . '/../vendor/autoload.php';
$result   = new WhichBrowser\Parser('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = new WhichBrowser\Parser($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => !empty($r->browser->name) ? $r->browser->name : null,
                'version' => !empty($r->browser->version) ? $r->browser->version->value : null,
            ],
            'platform' => [
                'name'    => !empty($r->os->name) ? $r->os->name : null,
                'version' => !empty($r->os->version->value) ? $r->os->version->value : null,
            ],
            'device' => [
                'name'     => !empty($r->device->model) ? $r->device->model : null,
                'brand'    => !empty($r->device->manufacturer) ? $r->device->manufacturer : null,
                'type'     => !empty($r->device->type) ? $r->device->type : null,
                'ismobile' => $r->isMobile() ? true : false,
            ],
        ],
        'time' => $end,
    ];
}

$file = null;

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('whichbrowser/parser');

echo (new \JsonClass\Json())->encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
