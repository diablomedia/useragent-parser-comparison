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
$parser = UAParser\Parser::create();
$parser->parse('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = $parser->parse($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $r->ua->family,
                'version' => $r->ua->toVersion(),
            ],
            'platform' => [
                'name'    => $r->os->family,
                'version' => $r->ua->toVersion(),
            ],
            'device' => [
                'name'     => null === $r->device->model ? '' : $r->device->model,
                'brand'    => null === $r->device->brand ? '' : $r->device->brand,
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
$package = new \PackageInfo\Package('ua-parser/uap-php');

$regexVersion = file_get_contents(__DIR__ . '/../version.txt');

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion() . '-' . $regexVersion,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
