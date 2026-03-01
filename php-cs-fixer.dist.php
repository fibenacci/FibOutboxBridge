<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$paths = [
    __DIR__ . '/src',
];

if (is_dir(__DIR__ . '/tests')) {
    $paths[] = __DIR__ . '/tests';
}

$finder = PhpCsFixer\Finder::create()->in($paths);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile('.build/cache/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP81Migration' => true,
        '@PSR2' => true,
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'phpdoc_summary' => false,
        'phpdoc_types_order' => true,
        'return_assignment' => false,
        'phpdoc_align' => [
            'align' => 'vertical',
            'tags' => [
                'param',
                'property',
                'property-read',
                'property-write',
                'return',
                'throws',
                'type',
                'var',
                'method',
            ],
        ],
        'phpdoc_to_comment' => false,
        'phpdoc_var_without_name' => false,
        'multiline_whitespace_before_semicolons' => true,
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'do',
                'exit',
                'if',
                'return',
                'switch',
                'try',
                'yield',
            ],
        ],
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '=' => 'align_single_space_minimal',
            ],
        ],
        'phpdoc_to_return_type' => true,
        'declare_strict_types' => true,
        'ternary_to_null_coalescing' => true,
        'void_return' => true,
        'visibility_required' => [
            'elements' => [
                'const',
                'property',
                'method',
            ],
        ],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        'native_type_declaration_casing' => false,
    ]);
