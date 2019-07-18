<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once __DIR__ . '/../vendor/autoload.php';

$tests = [];

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.yaml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/woothee/woothee-testset/testsets');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'yaml') {
        continue;
    }

    $provider = Spyc::YAMLLoad($fixture->getPathname());

    foreach ($provider as $data) {
        $ua = $data['target'];
        if (!empty($ua)) {
            $expected = [
                'browser' => [
                    'name'    => $data['name'] ?? null,
                    'version' => $data['version'] ?? null,
                ],
                'platform' => [
                    'name'    => $data['os'] ?? null,
                    'version' => $data['os_version'] ?? null,
                ],
                'device' => [
                    'name'     => null,
                    'brand'    => null,
                    'type'     => $data['category'] ?? null,
                    'ismobile' => null,
                ],
            ];

            $tests[$ua] = $expected;
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('woothee/woothee-testset');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
