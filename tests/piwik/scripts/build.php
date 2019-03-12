<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\DeviceParserAbstract;

require_once __DIR__ . '/../vendor/autoload.php';

$tests = [];

// These functions are adapted from DeviceDetector's source
// Didn't want to use the actual classes here due to performance and consideration of what we're actually testing
// (i.e. how can the parser ever fail on this field if the parser is generating it)
function isMobile($data): bool
{
    $device     = $data['device']['type'];
    $os         = $data['os']['short_name'];
    $deviceType = DeviceParserAbstract::getAvailableDeviceTypes()[$device];

    // Mobile device types
    if (!empty($deviceType) && in_array($deviceType, [
            DeviceParserAbstract::DEVICE_TYPE_FEATURE_PHONE,
            DeviceParserAbstract::DEVICE_TYPE_SMARTPHONE,
            DeviceParserAbstract::DEVICE_TYPE_TABLET,
            DeviceParserAbstract::DEVICE_TYPE_PHABLET,
            DeviceParserAbstract::DEVICE_TYPE_CAMERA,
            DeviceParserAbstract::DEVICE_TYPE_PORTABLE_MEDIA_PAYER,
        ])
    ) {
        return true;
    }

    // non mobile device types
    if (!empty($deviceType) && in_array($deviceType, [
            DeviceParserAbstract::DEVICE_TYPE_TV,
            DeviceParserAbstract::DEVICE_TYPE_SMART_DISPLAY,
            DeviceParserAbstract::DEVICE_TYPE_CONSOLE,
        ])
    ) {
        return false;
    }

    // Check for browsers available for mobile devices only
    if ($data['client']['type'] === 'browser' && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
        return true;
    }

    if (empty($os) || $os === 'UNK') {
        return false;
    }

    return !isDesktop($data);
}

function isDesktop($data): bool
{
    $osShort = $data['os']['short_name'];
    if (empty($osShort) || $osShort === 'UNK') {
        return false;
    }
    // Check for browsers available for mobile devices only
    if ($data['client']['type'] === 'browser' && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
        return false;
    }

    return in_array($data['os_family'], ['AmigaOS', 'IBM', 'GNU/Linux', 'Mac', 'Unix', 'Windows', 'BeOS', 'Chrome OS']);
}

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.yml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/piwik/device-detector/Tests/fixtures');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'yml') {
        continue;
    }

    $provider = Spyc::YAMLLoad($fixture->getPathname());

    foreach ($provider as $data) {
        // If no client property, may be in bot file, which we're not parsing just yet
        if (isset($data['client'])) {
            $ua = $data['user_agent'];
            if (!empty($ua)) {
                $expected = [
                    'browser' => [
                        'name'    => $data['client']['name'],
                        'version' => $data['client']['version'],
                    ],
                    'platform' => [
                        'name'    => $data['os']['name'],
                        'version' => $data['os']['version'],
                    ],
                    'device' => [
                        'name'     => (string) $data['device']['model'],
                        'brand'    => DeviceParserAbstract::getFullName($data['device']['brand']),
                        'type'     => $data['device']['type'],
                        'ismobile' => isMobile($data),
                    ],
                ];

                $tests[$ua] = $expected;
            }
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('piwik/device-detector');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
