<?php

declare(strict_types = 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$benchmarkPos = array_search('--benchmark', $argv);
$benchmark    = false;

if (false !== $benchmarkPos) {
    $benchmark = true;
    unset($argv[$benchmarkPos]);
    $argv = array_values($argv);
}

$agentListFile = $argv[1];

$results   = [];
$parseTime = 0;

$start = microtime(true);
require __DIR__ . '/../vendor/autoload.php';
UserAgentFactory::analyze('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = UserAgentFactory::analyze($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => !empty($r->browser['name']) ? $r->browser['name'] : '',
                'version' => !empty($r->browser['version']) ? $r->browser['version'] : '',
            ],
            'platform' => [
                'name'    => !empty($r->os['name']) ? $r->os['name'] : '',
                'version' => !empty($r->os['version']) ? $r->os['version'] : '',
            ],
            'device' => [
                'name'     => !empty($r->device['model']) ? $r->device['model'] : '',
                'brand'    => !empty($r->device['brand']) ? $r->device['brand'] : '',
                'type'     => null,
                'ismobile' => null,
            ],
        ],
        'time' => $end,
    ];
}

$file = null;

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('zsxsoft/php-useragent');

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
