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
$cacheDir  = __DIR__ . '/../data';
$fileCache = new \Doctrine\Common\Cache\FilesystemCache($cacheDir);
$cache     = new \Roave\DoctrineSimpleCache\SimpleCacheAdapter($fileCache);
$logger    = new \Psr\Log\NullLogger();
$factory   = new \BrowserDetector\DetectorFactory($cache, $logger);
$detector  = $factory();
$detector('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = $detector($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
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
}

$file = null;

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('mimmi20/browser-detector');

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_UNESCAPED_SLASHES);
