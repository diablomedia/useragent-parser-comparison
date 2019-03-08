<?php

declare(strict_types = 1);
$tests = [];

require_once __DIR__ . '/../vendor/autoload.php';

$finder = new \Symfony\Component\Finder\Finder();
$finder->files();
$finder->name('*.php');
$finder->ignoreDotFiles(true);
$finder->ignoreVCS(true);
$finder->sortByName();
$finder->ignoreUnreadableDirs();
$finder->in(__DIR__ . '/../files');

foreach ($finder as $file) {
    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $provider = include $file->getPathname();

    foreach ($provider as $ua => $properties) {
        $tests[$ua] = $properties;
    }
}

echo (new \JsonClass\Json())->encode([
    'tests' => $tests,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
