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

$jsonParser = new \Seld\JsonLint\JsonParser();

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
    if (!$fixture->isFile() || 'json' !== $fixture->getExtension()) {
        continue;
    }

    $filepath = $fixture->getPathname();

    $content = file_get_contents($filepath);

    if ('' === $content || PHP_EOL === $content) {
        continue;
    }

    try {
        $provider = $jsonParser->parse(
            $content,
            \Seld\JsonLint\JsonParser::DETECT_KEY_CONFLICTS | \Seld\JsonLint\JsonParser::PARSE_TO_ASSOC
        );
    } catch (\Seld\JsonLint\ParsingException $e) {
        continue;
    }

    $providerName = $fixture->getFilename();

    foreach ($provider as $data) {
        $ua = $data['ua'];

        if (!isset($uas[$ua])) {
            $uas[$ua] = $base;
        }

        switch ($providerName) {
            case 'browser-test.json':
                $uas[$ua]['browser']['name']    = 'undefined' === $data['expect']['name'] ? '' : $data['expect']['name'];
                $uas[$ua]['browser']['version'] = 'undefined' === $data['expect']['version'] ? '' : $data['expect']['version'];

                break;
            case 'device-test.json':
                $uas[$ua]['device']['name']  = 'undefined' === $data['expect']['model'] ? '' : $data['expect']['model'];
                $uas[$ua]['device']['brand'] = 'undefined' === $data['expect']['vendor'] ? '' : $data['expect']['vendor'];
                $uas[$ua]['device']['type']  = 'undefined' === $data['expect']['type'] ? '' : $data['expect']['type'];

                break;
            case 'os-test.json':
                $uas[$ua]['platform']['name']    = 'undefined' === $data['expect']['name'] ? '' : $data['expect']['name'];
                $uas[$ua]['platform']['version'] = 'undefined' === $data['expect']['version'] ? '' : $data['expect']['version'];

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
