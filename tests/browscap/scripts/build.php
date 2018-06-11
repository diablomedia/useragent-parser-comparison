<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.php');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/browscap/browscap/tests/issues');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'php') {
        continue;
    }

    if (in_array($fixture->getFilename(), ['issue-000-invalids.php', 'issue-000-invalid-versions.php'])) {
        continue;
    }

    $provider = include $fixture->getPathName();

    foreach ($provider as $testName => $data) {
        if ($data['full'] === false) {
            continue;
        }

        $ua = $data['ua'];

        if (!empty($ua)) {
            $isMobile = false;

            switch ($data['properties']['Device_Type']) {
                case 'Mobile Phone':
                case 'Tablet':
                case 'Console':
                case 'Digital Camera':
                case 'Ebook Reader':
                case 'Mobile Device':
                    $isMobile = true;

                    break;
            }

            $expected = [
                'browser' => [
                    'name'    => $data['properties']['Browser'],
                    'version' => $data['properties']['Version'],
                ],
                'platform' => [
                    'name'    => $data['properties']['Platform'] ?? 'unknown',
                    'version' => $data['properties']['Platform_Version'],
                ],
                'device' => [
                    'name'     => $data['properties']['Device_Name'],
                    'brand'    => $data['properties']['Device_Maker'],
                    'type'     => $data['properties']['Device_Type'],
                    'ismobile' => $isMobile,
                ],
            ];

            $tests[$ua] = $expected;
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('browscap/browscap');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
