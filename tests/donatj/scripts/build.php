<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$jsonParser = new \Seld\JsonLint\JsonParser();

$content = file_get_contents(__DIR__ . '/../vendor/donatj/phpuseragentparser/Tests/user_agents.json');

if ($content === '' || $content === PHP_EOL) {
    exit;
}

try {
    $provider = $jsonParser->parse(
        $content,
        \Seld\JsonLint\JsonParser::DETECT_KEY_CONFLICTS | \Seld\JsonLint\JsonParser::PARSE_TO_ASSOC
    );
} catch (\Seld\JsonLint\ParsingException $e) {
    exit;
}

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
