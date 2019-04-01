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

$defaultExpected = [
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

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'xml') {
        continue;
    }

    $provider = simplexml_load_file($fixture->getPathname());

    foreach ($provider->test as $test) {
        if ($fixture->getFilename() === 'browser.xml') {
            if ($test->CheckList->Item->Property->__toString() === 'Browser->getName()') {
                $expected = [
                    'browser' => [
                        'name' => $test->CheckList->Item->Value->__toString(),
                    ],
                ];
            }
        } elseif ($fixture->getFilename() === 'device.xml') {
            $expected = [];
            foreach ($test->CheckList->Item as $item) {
                if ($item->Property->__toString() === 'Device->getName()') {
                    $expected['device']['name'] = $item->Value->__toString();
                } elseif ($item->Property->__toString() === 'isMobile') {
                    $expected['device']['ismobile'] = $item->Value->__toString() === 'true' ? true : false;
                } elseif ($item->Property->__toString() === 'Device->getType()') {
                    $expected['device']['type'] = $item->Value->__toString();
                }
            }
        } elseif ($fixture->getFilename() === 'os.xml') {
            if ($test->CheckList->Item->Property->__toString() === 'OS->getName()') {
                $expected = [
                    'platform' => [
                        'name' => $test->CheckList->Item->Value->__toString(),
                    ],
                ];
            }
        } elseif ($fixture->getFilename() === 'issues.xml') {
            $name = '';
            foreach ($test->CheckList->Item as $item) {
                if ($item->Property->__toString() === 'OS->getName()') {
                    $name = $item->Value->__toString() . $name;
                } elseif ($item->Property->__toString() === 'OS->getVersion()') {
                    $name = $name . $item->Value->__toString();
                }
            }
            $expected = [
                'platform' => [
                    'name' => $name,
                ],
            ];
        } elseif ($fixture->getFilename() === 'robot.xml') {
            $expected = [];
            foreach ($test->CheckList->Item as $item) {
                if ($item->Property->__toString() === 'Robot->getName()') {
                    $expected['browser']['name'] = $item->Value->__toString();
                }
            }
        } elseif ($fixture->getFilename() === 'models.xml') {
            $osName   = '';
            $expected = [];
            foreach ($test->CheckList->Item as $item) {
                if ($item->Property->__toString() === 'OS->getName()') {
                    $osName = $item->Value->__toString() . $osName;
                } elseif ($item->Property->__toString() === 'Device->getName()') {
                    $expected['device']['name'] = $item->Value->__toString();
                } elseif ($item->Property->__toString() === 'Device->getModelName()') {
                    $expected['device']['brand'] = $item->Value->__toString();
                } elseif ($item->Property->__toString() === 'OS->getVersion()') {
                    $osName = $osName . $item->Value->__toString();
                }
            }

            $expected['platform']['name'] = $osName;
        } else {
            $expected = [];
        }

        if (empty($expected)) {
            continue;
        }

        foreach ($test->UAList->UA as $ua) {
            $agent = (string) $ua;

            if (isset($uas[$agent])) {
                $uas[$agent] = array_merge($uas[$agent], $expected);
            } else {
                $uas[$agent] = array_merge($defaultExpected, $expected);
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
