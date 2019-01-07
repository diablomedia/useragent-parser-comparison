<?php

declare(strict_types = 1);
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
    ->in(__DIR__ . '/tests/curated/files/*')
    ->in(__DIR__ . '/parsers')
    ->exclude('vendor')
    ->append([__FILE__]);

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2'                     => true,
        '@Symfony'                  => true,
        '@Symfony:risky'            => true,
        '@PHP70Migration'           => true,
        '@PHP70Migration:risky'     => true,
        '@PHP71Migration'           => true,
        '@PHP71Migration:risky'     => true,
        '@PHPUnit60Migration:risky' => true,

        // @PSR2 rules configured different from default
        'class_definition'      => ['singleLine' => false, 'singleItemSingleLine' => true, 'multiLineExtendsEachSingleLine' => true],
        'method_argument_space' => ['ensure_fully_multiline' => true, 'keep_multiple_spaces_after_comma' => false],
        'no_break_comment'      => false,
        'visibility_required'   => ['elements' => ['property', 'method', 'const']],

        // @Symfony rules configured different from default
        'binary_operator_spaces'  => ['default' => 'align_single_space_minimal'],
        'concat_space'            => ['spacing' => 'one'],
        'fopen_flags'             => ['b_mode' => true],
        'declare_equal_normalize' => ['space' => 'single'],
        'phpdoc_no_empty_return'  => false,
        'phpdoc_summary'          => false,
        'space_after_semicolon'   => ['remove_in_empty_for_expressions' => true],
        'yoda_style'              => ['equal' => false, 'identical' => false, 'less_and_greater' => false],

        // @PHP70Migration:risky rules configured different from default
        'pow_to_exponentiation' => false,

        // @PHPUnit60Migration:risky rules configured different from default
        'php_unit_dedicate_assert' => ['target' => 'newest'],

        // other rules
        'align_multiline_comment'                   => ['comment_type' => 'all_multiline'],
        'array_syntax'                              => ['syntax' => 'short'],
        'backtick_to_shell_exec'                    => true,
        'class_keyword_remove'                      => false,
        'combine_consecutive_issets'                => true,
        'combine_consecutive_unsets'                => true,
        'compact_nullable_typehint'                 => true,
        'escape_implicit_backslashes'               => ['double_quoted' => true, 'heredoc_syntax' => true, 'single_quoted' => false],
        'explicit_indirect_variable'                => true,
        'explicit_string_variable'                  => true,
        'final_internal_class'                      => ['annotation-black-list' => ['@final', '@Entity', '@ORM'], 'annotation-white-list' => ['@internal']],
        'general_phpdoc_annotation_remove'          => ['expectedExceptionMessageRegExp', 'expectedException', 'expectedExceptionMessage', 'author'],
        'hash_to_slash_comment'                     => true,
        'header_comment'                            => false,
        'heredoc_to_nowdoc'                         => true,
        'linebreak_after_opening_tag'               => true,
        'list_syntax'                               => ['syntax' => 'short'],
        'mb_str_functions'                          => true,
        'method_chaining_indentation'               => true,
        'method_separation'                         => true,
        'native_function_invocation'                => false,
        'no_multiline_whitespace_before_semicolons' => true,
        'no_null_property_initialization'           => true,
        'no_php4_constructor'                       => true,
        'no_short_echo_tag'                         => true,
        'no_superfluous_elseif'                     => true,
        'no_superfluous_phpdoc_tags'                => true,
        'no_unreachable_default_argument_value'     => true,
        'no_useless_else'                           => true,
        'no_useless_return'                         => false,
        'not_operator_with_space'                   => false,
        'not_operator_with_successor_space'         => false,
        'ordered_class_elements'                    => false,
        'ordered_imports'                           => true,
        'php_unit_test_annotation'                  => ['case' => 'camel', 'style' => 'prefix'],
        'php_unit_test_class_requires_covers'       => false,
        'phpdoc_add_missing_param_annotation'       => ['only_untyped' => true],
        'phpdoc_order'                              => true,
        'phpdoc_types_order'                        => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'alpha'],
        'psr0'                                      => true,
        'simplified_null_return'                    => false,
        'static_lambda'                             => true,
        'strict_comparison'                         => true,
        'strict_param'                              => false,
    ])
    ->setUsingCache(true)
    ->setFinder($finder);
