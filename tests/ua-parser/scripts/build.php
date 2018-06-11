<?php

declare(strict_types = 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../vendor/autoload.php';

$tests = [];

$cache = new Doctrine\Common\Cache\PhpFileCache(__DIR__ . '/../cache');

function processFixture($fixture, &$tests, $cache): void
{
    $key = sha1_file($fixture->getPathname());
    if ($cache->contains($key)) {
        $records = $cache->fetch($key);

        foreach ($records as $ua => $data) {
            $ua = addcslashes($ua, "\n");
            if (!isset($tests[$ua])) {
                $tests[$ua] = [
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
                        'brand'    => null,
                        'type'     => null,
                        'ismobile' => null,
                    ],
                ];
            }

            $tests[$ua] = array_replace_recursive($tests[$ua], $data);
        }
    } else {
        $provider = Yaml::parse(file_get_contents($fixture->getPathname()));

        $records = [];

        foreach ($provider['test_cases'] as $data) {
            $ua = $data['user_agent_string'];
            if (!empty($ua)) {
                if (isset($tests[addcslashes($ua, "\n")])) {
                    $browser  = $tests[$ua]['browser'];
                    $platform = $tests[$ua]['platform'];
                    $device   = $tests[$ua]['device'];
                } else {
                    $browser = [
                        'name'    => null,
                        'version' => null,
                    ];

                    $platform = [
                        'name'    => null,
                        'version' => null,
                    ];

                    $device = [
                        'name'     => null,
                        'brand'    => null,
                        'type'     => null,
                        'ismobile' => null,
                    ];
                }

                switch ($fixture->getFilename()) {
                    case 'test_device.yaml':
                        $device = [
                            'name'     => $data['model'],
                            'brand'    => $data['brand'],
                            'type'     => null,
                            'ismobile' => null,
                        ];

                        $records[$ua]['device'] = $device;

                        break;
                    case 'test_os.yaml':
                    case 'additional_os_tests.yaml':
                        $platform = [
                            'name'    => $data['family'],
                            'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                        ];

                        $records[$ua]['platform'] = $platform;

                        break;
                    case 'test_ua.yaml':
                    case 'firefox_user_agent_strings.yaml':
                    case 'opera_mini_user_agent_strings.yaml':
                    case 'pgts_browser_list.yaml':
                        $browserVersion = (isset($data['major']) && $data['major'] !== '') ? $data['major'] . ($data['minor'] !== null ? '.' . $data['minor'] : '') : '';
                        if ($browserVersion === '0') {
                            $browserVersion = '';
                        }
                        $browser = [
                            'name'    => $data['family'],
                            'version' => $browserVersion,
                        ];

                        $records[$ua]['browser'] = $browser;

                        break;
                }

                $expected = [
                    'browser'  => $browser,
                    'platform' => $platform,
                    'device'   => $device,
                ];

                $tests[addcslashes($ua, "\n")] = $expected;
            }
        }

        $cache->save($key, $records);
    }
}

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.yaml');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../node_modules/uap-core/tests');
// Supplemental files in tests_resources dir
$finder->in(__DIR__ . '/../node_modules/uap-core/test_resources');

foreach ($finder as $fixture) {
    /** @var \Symfony\Component\Finder\SplFileInfo $fixture */
    if (!$fixture->isFile() || $fixture->getExtension() !== 'yaml') {
        continue;
    }

    if ($fixture->getFilename() === 'pgts_browser_list-orig.yaml') {
        continue;
    }

    processFixture($fixture, $tests, $cache);
}

echo json_encode([
    'tests'   => $tests,
    'version' => file_get_contents(__DIR__ . '/../version.txt'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
