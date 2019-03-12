<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$content = file_get_contents(__DIR__ . '/../vendor/donatj/phpuseragentparser/Tests/user_agents.json');

if ($content === '' || $content === PHP_EOL) {
    exit;
}

$provider = json_decode($content, true);

foreach ($provider as $ua => $data) {
    if (!empty($ua)) {
        $expected = [
            'browser' => [
                'name'    => $data['browser'],
                'version' => $data['version'],
            ],
            'platform' => [
                'name'    => $data['platform'],
                'version' => null,
            ],
            'device' => [
                'name'     => null,
                'brand'    => null,
                'type'     => null,
                'ismobile' => null,
            ],
        ];

        $tests[$ua] = $expected;
    }
}

// Get version from composer
$package = new \PackageInfo\Package('donatj/phpuseragentparser');

echo json_encode([
    'tests'   => $tests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
