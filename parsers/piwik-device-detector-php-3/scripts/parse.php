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
use DeviceDetector\DeviceDetector;

$dd = new DeviceDetector('Test String');
$dd->skipBotDetection();
$dd->parse();
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $dd->setUserAgent($agentString);

    $start = microtime(true);
    $dd->parse();
    $end = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $clientInfo['name'] ?? null,
                'version' => (!empty($clientInfo['version']) ? $clientInfo['version'] : null),
            ],
            'platform' => [
                'name'    => $osInfo['name'] ?? null,
                'version' => (!empty($osInfo['version']) ? $osInfo['version'] : null),
            ],
            'device' => [
                'name'     => $model ? $model : null,
                'brand'    => $brand ? $brand : null,
                'type'     => $device ? $device : null,
                'ismobile' => $isMobile ? true : false,
            ],
        ],
        'time' => $end,
    ];
}

$file = null;

// Get version from composer
$package = new \PackageInfo\Package('piwik/device-detector');

echo (new \JsonClass\Json())->encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
