<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Would like to have name/brand for mobile devices, but their test suite doesn't break that out very well
// for the expected values.  Using the device parser class to extract possible device brand names that we
// can use to extract brand/name from the device "title" later. Would be nice to use tokens here rather than
// regex, but a task for another day.

$tests = [];

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

require __DIR__ . '/../vendor/autoload.php';

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.yml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/yzalis/ua-parser/tests/UAParser/Tests/Fixtures');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'yml') {
        continue;
    }

    $providerName = $fixture->getFilename();

    if (!in_array($providerName, ['browsers.yml', 'devices.yml', 'operating_systems.yml'])) {
        continue;
    }

    $provider = Spyc::YAMLLoad($fixture->getPathname());

    foreach ($provider as $data) {
        $ua = $data[0];

        if (!isset($tests[$ua])) {
            $tests[$ua] = $base;
        }

        switch ($providerName) {
            case 'browsers.yml':
                $tests[$ua]['browser']['name']    = $data[1];
                $tests[$ua]['browser']['version'] = $data[2] . ($data[3] !== null ? '.' . $data[3] . ($data[4] !== null ? '.' . $data[4] : null) : null);

                break;
            case 'devices.yml':
                $tests[$ua]['device']['name']  = $data[2];
                $tests[$ua]['device']['brand'] = $data[1];
                $tests[$ua]['device']['type']  = $data[3];

                break;
            case 'operating_systems.yml':
                $tests[$ua]['platform']['name']    = $data[1];
                $tests[$ua]['platform']['version'] = null;

                break;
            // Skipping rendering_engines.yml because we don't look at Engine data
            // Skipping other files because we dont test this
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('yzalis/ua-parser');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
