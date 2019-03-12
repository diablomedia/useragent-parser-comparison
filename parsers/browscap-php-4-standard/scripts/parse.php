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
$logger    = new \Psr\Log\NullLogger('null');
$bc        = new \BrowscapPHP\Browscap($cache, $logger);
$bc->getBrowser('Test String');
$initTime = microtime(true) - $start;

$file = new SplFileObject($agentListFile);
$file->setFlags(SplFileObject::DROP_NEW_LINE);

while (!$file->eof()) {
    $agentString = $file->fgets();

    if (empty($agentString)) {
        continue;
    }

    $start = microtime(true);
    $r     = $bc->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $parseTime += $end;

    if ($benchmark) {
        continue;
    }

    $results[] = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $r->browser,
                'version' => ($r->version === '0.0' ? null : $r->version),
            ],
            'platform' => [
                'name'    => $r->platform,
                'version' => ($r->platform_version === '0.0' ? null : $r->platform_version),
            ],
            'device' => [
                'name'     => null,
                'brand'    => null,
                'type'     => $r->device_type,
                'ismobile' => $r->ismobiledevice ? true : false,
            ],
        ],
        'time' => $end,
    ];
}

$file = null;

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('browscap/browscap-php');

$bcCache = new \BrowscapPHP\Cache\BrowscapCache($cache, $logger);

echo json_encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion() . '-' . $bcCache->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
