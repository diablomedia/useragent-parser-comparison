<?php

declare(strict_types = 1);
$uas = [];

require_once __DIR__ . '/../vendor/autoload.php';

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.xml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/endorphin-studio/browser-detector/tests/yaml');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'xml') {
        continue;
    }

    $provider = simplexml_load_file($fixture->getPathname());

    foreach ($provider->test as $test) {
        $expected = [
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

        foreach ($test->uaList as $ua) {
            $agent = (string) $ua;

            if (isset($uas[$agent])) {
                continue;
            }

            $uas[$agent] = $expected;
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('endorphin-studio/browser-detector');

echo (new \JsonClass\Json())->encode([
    'tests'   => $uas,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
