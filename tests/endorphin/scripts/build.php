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
$finder->in(__DIR__ . '/../vendor/endorphin-studio/browser-detector/tests/data/ua');

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

        foreach ($test->CheckList as $list) {
            foreach ($list->Item as $item) {
                switch ($item->Property) {
                    case 'OS->getName()':
                        $expected['platform']['name'] = (string) $item->Value;

                        break;
                    case 'OS->getVersion()':
                        $expected['platform']['version'] = (string) $item->Value;

                        break;
                    case 'Browser->getName()':
                        $expected['browser']['name'] = (string) $item->Value;

                        break;
                    case 'Device->getName()':
                        $expected['device']['name'] = (string) $item->Value;

                        break;
                    case 'Device->getType()':
                        $expected['device']['type'] = (string) $item->Value;

                        break;
                    case 'isMobile':
                        $expected['device']['ismobile'] = (bool) $item->Value ? true : false;

                        break;
                    case 'Robot->getName()':
                        $expected['browser']['name'] = (string) $item->Value;

                        break;
                }
            }
        }

        foreach ($test->UAList->UA as $ua) {
            $agent = (string) $ua;

            if (!isset($uas[$agent])) {
                $uas[$agent] = $expected;
            } else {
                $toInsert             = $expected;
                $toInsert['browser']  = array_filter($expected['browser']);
                $toInsert['platform'] = array_filter($expected['platform']);
                $toInsert['device']   = array_filter($expected['device']);
                $toInsert             = array_filter($expected);
                $uas[$agent]          = array_replace_recursive($uas[$agent], $toInsert);
            }
        }
    }
}

// Get version from composer
$package = new \PackageInfo\Package('endorphin-studio/browser-detector');

echo json_encode([
    'tests'   => $uas,
    'version' => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
