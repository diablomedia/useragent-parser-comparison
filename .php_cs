<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('cache')
    ->exclude('data')
    ->exclude('files')
    ->exclude('node_modules')
    ->files()
    ->name('console')
    ->name('build')
    ->name('parse')
    ->name('*.php')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/bin')
    ->in(__DIR__ . '/mappings')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/parsers')
;

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'binary_operator_spaces' => ['align_double_arrow' => true, 'align_equals' => true],
        'single_quote' => false,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'dir_constant' => true,
    ])
    ->setUsingCache(true)
    ->setFinder($finder);
;
