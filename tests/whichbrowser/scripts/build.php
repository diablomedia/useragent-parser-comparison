<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$tests = [];

function isMobile($data)
{
    if (!isset($data['device']['type'])) {
        return null;
    }

    $mobileTypes = ['mobile', 'tablet', 'ereader', 'media', 'watch', 'camera'];

    if (in_array($data['device']['type'], $mobileTypes)) {
        return true;
    }

    if ($data['device']['type'] === 'gaming') {
        if (isset($data['device']['subtype']) && $data['device']['subtype'] === 'portable') {
            return true;
        }
    }

    return false;
}

$uas = [];

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.yaml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/whichbrowser/parser/tests/data');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || 'yaml' !== $fixture->getExtension()) {
        continue;
    }

    $provider = Yaml::parse(file_get_contents($fixture->getPathname()));

    foreach ($provider as $data) {
        if (isset($data['useragent'])) {
            // The behavior of the parser on records with this field seems to be inconsistent
            // sometimes it uses the value here, other times it uses the User-Agent header in the 'headers'
            // property. Excluding for now.
            continue;
        }

        if (is_array($data['headers']) && !empty($data['headers']['User-Agent'])) {
            if (1 < count($data['headers'])) {
                // Ignoring the ones that have the additional headers since we can't guarantee the expected value
                // for those cases (assuming that whichbrowser changes some data based on those headers).
                continue;
            }
            $ua = $data['headers']['User-Agent'];
        } else {
            if (0 !== mb_strpos($data['headers'], 'User-Agent: ')) {
                // There are a few tests that don't have a "User-Agent:" header
                // discarding those since other parsers don't parse different headers in this comparison
                continue;
            }

            $ua = str_replace('User-Agent: ', '', $data['headers']);
        }

        $uas[$ua] = $data;
    }
}

foreach ($uas as $ua => $data) {
    if (!empty($ua)) {
        $data = $data['result'];

        $expected = [
            'browser' => [
                'name'    => $data['browser']['name'],
                'version' => is_array($data['browser']['version']) ? $data['browser']['version']['value'] : $data['browser']['version'],
            ],
            'platform' => [
                'name'    => $data['os']['name'],
                'version' => is_array($data['os']['version']) ? $data['os']['version']['value'] : $data['os']['version'],
            ],
            'device' => [
                'name'     => $data['device']['model'],
                'brand'    => $data['device']['manufacturer'],
                'type'     => $data['device']['type'],
                'ismobile' => isMobile($data) ? 'true' : 'false',
            ],
        ];

        $tests[$ua] = $expected;
    }
}

// Get version from composer
$package = new \PackageInfo\Package('whichbrowser/parser');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
