<?php
$header = <<<EOF
 RegenRadar Generator aus DWD-Radarbilder from neuthardwetter.de by Jens Dutzi

 @package    blog404de\RegenRadar
 @author     Jens Dutzi <jens.dutzi@tf-network.de>
 @copyright  Copyright (c) 2012-2020 Jens Dutzi (http://www.neuthardwetter.de)
 @license    https://github.com/Blog404DE/RegenRadarVideo/blob/master/LICENSE.md
 @version    3.3.1
 @link       https://github.com/Blog404DE/RegenRadarVideo
EOF;

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
$finder = PhpCsFixer\Finder::create()
    ->exclude('Resources')
    ->exclude('dev')
    ->in([__DIR__ . '/', __DIR__ . '/botLib']);

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
$config = new PhpCsFixer\Config();
$config->setRules([
        '@Symfony' => true,
        '@PSR1' => true,
        '@Symfony:risky' => true,
        '@PHP70Migration' => true,
        '@PHP70Migration:risky' => true,
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PHPUnit60Migration:risky' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'general_phpdoc_annotation_remove' => [
            'case_sensitive' => true
        ],
        'header_comment' => ['header' => $header],
        'linebreak_after_opening_tag' => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
        ],
        'concat_space' => [
            'spacing' => 'one'
        ],
		'cast_spaces' => [
            'space' => 'none'
        ],
        'curly_braces_position' => [
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'same_line',
            'anonymous_classes_opening_brace' => 'same_line',
        ],
        'backtick_to_shell_exec' => true,
        'mb_str_functions' => true,
        'no_php4_constructor' => true,
        'simplified_null_return' => true,
        'phpdoc_to_comment' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
        'phpdoc_types_order' => [
             'null_adjustment' => 'always_last',
             'sort_algorithm' => 'none',
        ],
        'no_superfluous_phpdoc_tags' => false
])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);

return $config;
