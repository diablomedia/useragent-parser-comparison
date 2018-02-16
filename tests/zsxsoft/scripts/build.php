<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once __DIR__ . '/../vendor/autoload.php';

// Would like to have name/brand for mobile devices, but their test suite doesn't break that out very well
// for the expected values.  Using the device parser class to extract possible device brand names that we
// can use to extract brand/name from the device "title" later. Would be nice to use tokens here rather than
// regex, but a task for another day.
$brands = [];
$file   = new SplFileObject(__DIR__ . '/../vendor/zsxsoft/php-useragent/lib/useragent_detect_device.php');
$file->setFlags(SplFileObject::DROP_NEW_LINE);
while (!$file->eof()) {
    $line = trim($file->fgets());
    preg_match('/^\$brand = ("|\')(.*)("|\');$/', $line, $matches);

    if (0 < count($matches)) {
        $brand = $matches[2];
        if (!empty($brand)) {
            $brands[] = $brand;
        }
    }
}
$brands = array_unique($brands);

usort($brands, static function ($a, $b) {
    return mb_strlen($b) - mb_strlen($a);
});

$provider = include __DIR__ . '/../vendor/zsxsoft/php-useragent/tests/UserAgentList.php';

$tests = [];

foreach ($provider as $data) {
    $ua = $data[0][0];
    if (!empty($ua)) {
        $brand = '';
        $model = '';

        foreach ($brands as $brand) {
            if (false !== mb_strpos($data[1][8], $brand)) {
                $model = trim(str_replace($brand, '', $data[1][8]));

                break;
            }
            $brand = '';
        }

        $expected = [
            'browser' => [
                'name'    => $data[1][2],
                'version' => $data[1][3],
            ],
            'platform' => [
                'name'    => $data[1][5],
                'version' => $data[1][6],
            ],
            'device' => [
                'name'     => $model,
                'brand'    => $brand,
                'type'     => null,
                'ismobile' => null,
            ],
        ];

        $tests[$ua] = $expected;
    }
}

// Get version from composer
$package = new \PackageInfo\Package('zsxsoft/php-useragent');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
