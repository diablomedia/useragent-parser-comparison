<?php

declare(strict_types = 1);

use Symfony\Component\Yaml\Yaml;

$uas = [];

require_once __DIR__ . '/../vendor/autoload.php';

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.yaml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/endorphin-studio/browser-detector/tests/yaml');

$defaultExpected = [
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
        'type'     => null,
        'brand'    => null,
        'ismobile' => null,
    ],
];

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'yaml') {
        continue;
    }

    $provider = Yaml::parse(file_get_contents($fixture->getPathname()));

    if (isset($provider['checkList']['name']) && strpos($fixture->getPathname(), '/browser/') !== false) {
        $expected = [
            'browser' => [
                'name' => $provider['checkList']['name'],
            ],
        ];
        
        if (isset($provider['checkList']['type'])) {
            $expected['device'] = [
                'type' => $provider['checkList']['type'],
            ];
        }
    } elseif (isset($provider['checkList']['name']) && strpos($fixture->getPathname(), '/device/') !== false) {
        $expected = [
            'device' => [
                'name' => $provider['checkList']['name'],
            ],
        ];
        
        if (isset($provider['checkList']['type'])) {
            $expected['device'] = [
                'type' => $provider['checkList']['type'],
            ];
        }
    } elseif (isset($provider['checkList']['name']) && strpos($fixture->getPathname(), '/os/') !== false) {
        if ($provider['checkList']['name'] === 'Windows') {
            $name = $provider['checkList']['name'] . $provider['checkList']['version'];
        } else {
            $name = $provider['checkList']['name'];
        }
        $expected = [
            'platform' => [
                'name' => $name,
            ],
        ];
    } elseif (isset($provider['checkList']['name']) && strpos($fixture->getPathname(), '/robot/') !== false) {
        $expected = [
            'browser' => [
                'name' => $provider['checkList']['name'],
            ],
        ];
    } else {
        $expected = [];
    }

    if (empty($expected)) {
        continue;
    }

    foreach ($provider['uaList'] as $ua) {
        $agent = (string) $ua;

        if (isset($uas[$agent])) {
            $uas[$agent] = array_merge($uas[$agent], $expected);
            //continue;
        } else {
            $uas[$agent] = array_merge($defaultExpected, $expected);
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('endorphin-studio/browser-detector');

echo (new \JsonClass\Json())->encode([
    'tests'   => $uas,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
