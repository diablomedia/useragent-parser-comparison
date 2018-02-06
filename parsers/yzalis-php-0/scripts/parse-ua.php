<?php

declare(strict_types = 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

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
$uaParser = new \UAParser\UAParser();
$uaParser->parse('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start  = microtime(true);
    $result = $uaParser->parse($agentString);
    $end    = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $result->getBrowser()->getFamily(),
                'version' => $result->getBrowser()->getVersionString(),
            ],
            'platform' => [
                'name'    => $result->getOperatingSystem()->getFamily(),
                'version' => !empty($r->os['version']) ? $r->os['version'] : '',
            ],
            'device' => [
                'name'     => $result->getDevice()->getModel(),
                'brand'    => $result->getDevice()->getConstructor(),
                'type'     => $result->getDevice()->getType(),
                'ismobile' => $result->getDevice()->isMobile(),
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$file = null;

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('yzalis/ua-parser');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
