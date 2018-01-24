<?php
declare(strict_types = 1);
echo 'Preparing Browscap Files' . PHP_EOL;

ini_set('memory_limit', '-1');

require __DIR__ . '/../vendor/autoload.php';

use phpbrowscap\Browscap;

$cacheDir               = __DIR__ . '/../data';
$browscap               = new Browscap($cacheDir);
$browscap->remoteIniUrl = 'http://browscap.org/stream?q=Full_PHP_BrowsCapINI';
$browscap->updateCache();

echo 'Finished' . PHP_EOL;
