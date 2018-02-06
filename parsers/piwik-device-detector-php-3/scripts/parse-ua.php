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
require_once __DIR__ . '/../vendor/autoload.php';
use DeviceDetector\DeviceDetector;

$dd = new DeviceDetector('Test String');
$dd->skipBotDetection();
$dd->parse();
$initTime = microtime(true) - $start;

if ($hasUa) {
    $dd->setUserAgent($agentString);

    $start = microtime(true);
    $dd->parse();
    $end = microtime(true) - $start;

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $clientInfo['name'] ?? null,
                'version' => $clientInfo['version'] ?? null,
            ],
            'platform' => [
                'name'    => $osInfo['name'] ?? null,
                'version' => $osInfo['version'] ?? null,
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

    $parseTime = $end;
}

$file = null;

// Get version from composer
$package = new \PackageInfo\Package('piwik/device-detector');

echo (new \JsonClass\Json())->encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
