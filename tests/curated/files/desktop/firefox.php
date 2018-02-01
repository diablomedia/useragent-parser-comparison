<?php

declare(strict_types = 1);

return [
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:51.0) Gecko/20100101 Firefox/51.0' => [
        'browser'  => ['name' => 'firefox', 'version' => '51.0'],
        'platform' => ['name' => 'macos', 'version' => '10.12'],
        'device'   => ['name' => 'macintosh', 'brand' => 'apple', 'type' => 'desktop', 'ismobile' => 'false'],
        'engine'   => ['name' => 'gecko', 'version' => '51.0'],
    ],
    'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0' => [
        'browser'  => ['name' => 'firefox', 'version' => '43.0'],
        'platform' => ['name' => 'windows', 'version' => '10.0'],
        'device'   => ['name' => '', 'brand' => '', 'type' => 'desktop', 'ismobile' => 'false'],
        'engine'   => ['name' => 'gecko', 'version' => '43.0'],
    ],
];
