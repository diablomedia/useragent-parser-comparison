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
$browser = new Browser();
$browser->setUserAgent('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $browser->setUserAgent($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $browser->getBrowser(),
                'version' => $browser->getVersion(),
            ],
            'platform' => [
                'name'    => $browser->getPlatform(),
                'version' => null,
            ],
            'device' => [
                'name'     => null,
                'brand'    => null,
                'type'     => null,
                'ismobile' => null,
            ],
        ],
        'time' => $end,
    ];
}

$file   = null;
$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('cbschuld/browser.php');

echo (new \JsonClass\Json())->encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
