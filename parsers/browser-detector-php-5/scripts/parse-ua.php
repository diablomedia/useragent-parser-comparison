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
$fileCache = new \Doctrine\Common\Cache\VoidCache();
$cache     = new \Roave\DoctrineSimpleCache\SimpleCacheAdapter($fileCache);
$logger    = new \Psr\Log\NullLogger();
$factory   = new \BrowserDetector\DetectorFactory($cache, $logger);
$detector  = $factory();
$detector('Test String');
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector($agentString);
    $end   = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $r->getBrowser()->getName(),
                'version' => $r->getBrowser()->getVersion()->getVersion(),
            ],
            'platform' => [
                'name'    => $r->getOs()->getName(),
                'version' => $r->getOs()->getVersion()->getVersion(),
            ],
            'device' => [
                'name'     => $r->getDevice()->getDeviceName(),
                'brand'    => $r->getDevice()->getBrand()->getBrandName(),
                'type'     => $r->getDevice()->getType()->getName(),
                'ismobile' => $r->getDevice()->getType()->isMobile() ? 'true' : 'false',
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$file = null;

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('mimmi20/browser-detector');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_UNESCAPED_SLASHES);
