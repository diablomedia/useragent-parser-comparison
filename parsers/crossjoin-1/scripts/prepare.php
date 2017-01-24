<?php

ini_set('memory_limit', -1);

require __DIR__ . '/../vendor/autoload.php';

$cacheDir = __DIR__ . '/../data/';
\Crossjoin\Browscap\Browscap::setDatasetType(\Crossjoin\Browscap\Browscap::DATASET_TYPE_LARGE);
\Crossjoin\Browscap\Cache\File::setCacheDirectory($cacheDir);
$browscap = new \Crossjoin\Browscap\Browscap();
$browscap->getBrowser()->getData();

$browscap->getUpdater()->setInterval(0);

// Run the browscap data update and preparation
$browscap->update();

echo 'Finished' . PHP_EOL;
