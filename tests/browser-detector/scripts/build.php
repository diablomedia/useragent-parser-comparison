<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$allTests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$logger     = new \Psr\Log\NullLogger();
$jsonParser = new \JsonClass\Json();

$companyLoaderFactory = new \BrowserDetector\Loader\CompanyLoaderFactory($jsonParser, new \BrowserDetector\Loader\Helper\Filter());

/** @var \BrowserDetector\Loader\CompanyLoader $companyLoader */
$companyLoader = $companyLoaderFactory();
$resultFactory = new \ResultFactory($companyLoader);

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.json');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../vendor/mimmi20/browser-detector/tests/data/');

foreach ($finder as $file) {
    $filepath = $file->getPathname();

    $content = $file->getContents();

    if ($content === '' || $content === PHP_EOL) {
        continue;
    }

    try {
        $data = $jsonParser->decode($content, true);
    } catch (\ExceptionalJSON\DecodeErrorException $e) {
        continue;
    }

    if (!is_array($data)) {
        continue;
    }

    foreach ($data as $test) {
        if (!is_array($test['headers']) || !isset($test['headers']['user-agent'])) {
            continue;
        }

        if (count($test['headers']) > 1) {
            // Ignoring the ones that have the additional headers since we can't guarantee the expected value
            // for those cases (assuming that whichbrowser changes some data based on those headers).
            continue;
        }

        if ($test['headers']['user-agent'] === 'this is a fake ua to trigger the fallback') {
            continue;
        }

        $agent = trim($test['headers']['user-agent']);

        if (array_key_exists($agent, $allTests)) {
            continue;
        }

        $expectedResult = $resultFactory->fromArray($logger, $test);
        $browserVersion = $expectedResult->getBrowser()->getVersion()->getVersion();
        $osVersion      = $expectedResult->getOs()->getVersion()->getVersion();

        $allTests[$agent] = [
            'browser' => [
                'name'    => $expectedResult->getBrowser()->getName(),
                'version' => ($browserVersion === '0.0.0' ? null : $browserVersion),
            ],
            'platform' => [
                'name'    => $expectedResult->getOs()->getName(),
                'version' => ($osVersion === '0.0.0' ? null : $osVersion),
            ],
            'device' => [
                'name'     => $expectedResult->getDevice()->getMarketingName(),
                'brand'    => $expectedResult->getDevice()->getBrand()->getBrandName(),
                'type'     => $expectedResult->getDevice()->getType()->getName(),
                'ismobile' => $expectedResult->getDevice()->getType()->isMobile(),
            ],
        ];
    }
}

// Get version from composer
$package = new \PackageInfo\Package('mimmi20/browser-detector');

echo json_encode([
    'tests'   => $allTests,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
