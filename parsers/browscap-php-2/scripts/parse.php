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
$cacheDir               = __DIR__ . '/../data';
$browscap               = new phpbrowscap\Browscap($cacheDir);
$browscap->doAutoUpdate = false;
// Initialize and include in init, but don't want to blow out the time on a real agent
// if cache loading and all that has to happen here
$browscap->getBrowser('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = $browscap->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
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
}

$file = null;

// Get version from composer
$package = new \PackageInfo\Package('browscap/browscap-php');

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => $package->getVersion() . '-' . $browscap->getSourceVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
