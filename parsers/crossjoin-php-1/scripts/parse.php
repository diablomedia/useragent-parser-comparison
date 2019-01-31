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
$cacheDir = __DIR__ . '/../data';
\Crossjoin\Browscap\Cache\File::setCacheDirectory($cacheDir);
\Crossjoin\Browscap\Browscap::setDatasetType(\Crossjoin\Browscap\Browscap::DATASET_TYPE_LARGE);
$updater = new \Crossjoin\Browscap\Updater\None();
\Crossjoin\Browscap\Browscap::setUpdater($updater);
$browscap = new \Crossjoin\Browscap\Browscap();
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
    $r     = $browscap->getBrowser($agentString)->getData();
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
                'version' => $r->version,
            ],
            'platform' => [
                'name'    => $r->platform,
                'version' => $r->platform_version,
            ],
            'device' => [
                'name'     => $r->device_name,
                'brand'    => $r->device_maker,
                'type'     => $r->device_type,
                'ismobile' => $r->ismobiledevice === 'true',
            ],
        ],
        'time' => $end,
    ];
}

$file = null;

// Get version from composer
$package = new \PackageInfo\Package('crossjoin/browscap');

echo (new \JsonClass\Json())->encode([
    'results'     => $results,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => memory_get_peak_usage(),
    'version'     => $package->getVersion() . '-' . \Crossjoin\Browscap\Browscap::getParser()->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
