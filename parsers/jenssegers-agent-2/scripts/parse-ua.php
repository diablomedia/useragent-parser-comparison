#!/usr/bin/env php
<?php

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

$uaPos       = array_search('--ua', $argv);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[1];
}

$result    = null;
$parseTime = 0;

$start = microtime(true);
require_once __DIR__ . '/vendor/autoload.php';

use Jenssegers\Agent\Agent;

$agent = new Agent();
$agent->setUserAgent('Test String');
$agent->isDesktop();
$initTime = microtime(true) - $start;

if ($hasUa) {
    $start = microtime(true);
    $agent->setUserAgent($agentString);
    $device          = $agent->device();
    $platform        = $agent->platform();
    $browser         = $agent->browser();
    $isMobile        = $agent->isMobile();
    $browserVersion  = $agent->version($browser);
    $platformVersion = $agent->version($platform);
    $type            = '';
    if ($agent->isDesktop()) {
        $type = 'desktop';
    } elseif ($agent->isPhone()) {
        $type = 'mobile phone';
    } elseif ($agent->isTablet()) {
        $type = 'tablet';
    } elseif ($agent->isBot()) {
        $type           = 'crawler';
        $browser        = $agent->robot() . ' Bot';
        $browserVersion = '';
    }
    $end = microtime(true) - $start;

    $result = [
        'useragent' => $agentString,
        'parsed'    => [
            'browser' => [
                'name'    => $browser,
                'version' => $browserVersion ? $browserVersion : '',
            ],
            'platform' => [
                'name'    => $platform,
                'version' => $platformVersion ? $platformVersion : '',
            ],
            'device' => [
                'name'     => $device,
                'brand'    => null,
                'type'     => $type,
                'ismobile' => $isMobile ? 'true' : 'false',
            ],
        ],
        'time' => $end,
    ];

    $parseTime = $end;
}

$memory = memory_get_peak_usage();

// Get version from composer
$package = new \PackageInfo\Package('jenssegers/agent');

echo json_encode([
    'result'      => $result,
    'parse_time'  => $parseTime,
    'init_time'   => $initTime,
    'memory_used' => $memory,
    'version'     => $package->getVersion(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
