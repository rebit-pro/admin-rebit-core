<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'declare_strict_types' => true,
        'no_superfluous_phpdoc_tags' => true,
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'array_syntax' => ['syntax' => 'short'],
        'protected_to_private' => false,
        'native_function_invocation' => false,
        'native_constant_invocation' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_align' => ['align' => 'left'],
        'function_declaration' => ['closure_function_spacing' => 'none', 'closure_fn_spacing' => 'none'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'php_unit_test_class_requires_covers' => false,
        'blank_line_before_statement' => [
            'statements' => ['return'],
        ],
    ])
    ->setFinder(
        Finder::create()
            ->in([
                __DIR__ . '/bin',
                __DIR__ . '/config',
                __DIR__ . '/public',
                __DIR__ . '/src',
                __DIR__ . '/tests',
            ]),
    )
;
