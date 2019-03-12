<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uas = [];

$base = [
    'browser' => [
        'name'    => null,
        'version' => null,
    ],
    'platform' => [
        'name'    => null,
        'version' => null,
    ],
    'device' => [
        'name'     => null,
        'brand'    => null,
        'type'     => null,
        'ismobile' => null,
    ],
];

require_once __DIR__ . '/../vendor/autoload.php';

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.json');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../node_modules/ua-parser-js/test');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'json') {
        continue;
    }

    $filepath = $fixture->getPathname();

    $content = file_get_contents($filepath);

    if ($content === '' || $content === PHP_EOL) {
        continue;
    }

    $provider     = json_decode($content, true);
    $providerName = $fixture->getFilename();

    foreach ($provider as $data) {
        $ua = $data['ua'];

        if (!isset($uas[$ua])) {
            $uas[$ua] = $base;
        }

        switch ($providerName) {
            case 'browser-test.json':
                $uas[$ua]['browser']['name']    = $data['expect']['name']    === 'undefined' ? null : $data['expect']['name'];
                $uas[$ua]['browser']['version'] = $data['expect']['version'] === 'undefined' ? null : $data['expect']['version'];

                break;
            case 'device-test.json':
                $uas[$ua]['device']['name']  = $data['expect']['model']  === 'undefined' ? null : $data['expect']['model'];
                $uas[$ua]['device']['brand'] = $data['expect']['vendor'] === 'undefined' ? null : $data['expect']['vendor'];
                $uas[$ua]['device']['type']  = $data['expect']['type']   === 'undefined' ? null : $data['expect']['type'];

                break;
            case 'os-test.json':
                $uas[$ua]['platform']['name']    = $data['expect']['name']    === 'undefined' ? null : $data['expect']['name'];
                $uas[$ua]['platform']['version'] = $data['expect']['version'] === 'undefined' ? null : $data['expect']['version'];

                break;
            // Skipping cpu-test.json because we don't look at CPU data, which is all that file tests against
            // Skipping engine-test.json because we don't look at Engine data
            // Skipping mediaplayer-test.json because it seems that this file isn't used in this project's actual tests (see test.js)
        }
    }
}

// Get version from installed module's package.json
$package = json_decode(file_get_contents(__DIR__ . '/../node_modules/ua-parser-js/package.json'));
$version = $package->version;

echo json_encode([
    'tests'   => $uas,
    'version' => $version,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
