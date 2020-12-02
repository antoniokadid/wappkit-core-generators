<?php

// https://github.com/FriendsOfPHP/PHP-CS-Fixer
// https://mlocati.github.io/php-cs-fixer-configurator

return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setRules([
        'array_indentation' => true,
        'array_syntax'      => [
            'syntax' => 'short'
        ],
        'binary_operator_spaces' => [
            'align_double_arrow' => true,
            'align_equals'       => true
        ],
        'constant_case'                               => ['case' => 'lower'],
        'encoding'                                    => true,
        'lowercase_cast'                              => true,
        'lowercase_keywords'                          => true,
        'magic_constant_casing'                       => true,
        'magic_method_casing'                         => true,
        'method_chaining_indentation'                 => true,
        'no_blank_lines_after_class_opening'          => true,
        'no_blank_lines_after_phpdoc'                 => true,
        'no_blank_lines_before_namespace'             => false,
        'no_closing_tag'                              => true,
        'no_empty_comment'                            => true,
        'no_empty_phpdoc'                             => true,
        'no_empty_statement'                          => true,
        'no_leading_import_slash'                     => true,
        'no_leading_namespace_whitespace'             => true,
        'no_singleline_whitespace_before_semicolons'  => true,
        'no_spaces_after_function_name'               => true,
        'no_spaces_around_offset'                     => [
            'positions' => ['inside', 'outside']
        ],
        'no_spaces_inside_parenthesis'          => true,
        'no_trailing_comma_in_list_call'        => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_trailing_whitespace'                => true,
        'no_trailing_whitespace_in_comment'     => true,
        'no_unneeded_control_parentheses'       => [
            'statements' => ['break', 'clone', 'continue', 'echo_print', 'return', 'switch_case', 'yield']
        ],
        'no_unneeded_curly_braces' => [
            'namespaces' => true
        ],
        'no_unused_imports'                   => true,
        'no_useless_else'                     => true,
        'no_useless_return'                   => true,
        'no_whitespace_before_comma_in_array' => [
            'after_heredoc' => true
        ],
        'no_whitespace_in_blank_line'                      => true,
        'ordered_class_elements'                           => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_protected_static',
                'property_protected',
                'property_private_static',
                'property_private',
                'construct',
                'destruct',
                'property_public_static',
                'property_public',
                'magic',
                'phpunit',
                'method_public_static',
                'method_public',
                'method_protected_static',
                'method_protected',
                'method_private_static',
                'method_private'
            ],
            'sortAlgorithm' => 'alpha'
        ],
        'ordered_imports' => [
            'imports_order'  => null,
            'sort_algorithm' => 'alpha'
        ],
        'phpdoc_align' => [
            'align' => 'vertical',
            'tags'  => [
                'param',
                'return',
                'throws',
                'type',
                'var'
            ]
        ],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent'                 => true,
        'phpdoc_inline_tag'             => true,
        'phpdoc_line_span'              => [
            'const'    => 'single',
            'method'   => 'multi',
            'property' => 'single'
        ],
        'phpdoc_no_access'                              => true,
        'phpdoc_no_alias_tag'                           => true,
        'phpdoc_no_empty_return'                        => true,
        'phpdoc_no_package'                             => false,
        'phpdoc_no_useless_inheritdoc'                  => true,
        'phpdoc_order'                                  => true,
        'phpdoc_return_self_reference'                  => true,
        'phpdoc_scalar'                                 => true,
        'phpdoc_separation'                             => true,
        'phpdoc_single_line_var_spacing'                => true,
        'phpdoc_summary'                                => true,
        'phpdoc_to_comment'                             => true,
        'phpdoc_trim'                                   => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types'                                  => true,
        'phpdoc_types_order'                            => [
            'null_adjustment' => 'always_first',
            'sort_algorithm'  => 'alpha'
        ],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name'             => true,
        'return_assignment'                   => true,
        'return_type_declaration'             => [
            'space_before' => 'none'
        ],
        'single_blank_line_at_eof'           => true,
        'single_blank_line_before_namespace' => true,
        'single_class_element_per_statement' => true,
        'single_import_per_statement'        => true,
        'single_line_after_imports'          => true,
        'single_line_comment_style'          => true,
        'single_line_throw'                  => true,
        'single_quote'                       => true,
        'single_trait_insert_per_statement'  => true,
        'space_after_semicolon'              => true,
        'standardize_not_equals'             => true,
        'switch_case_semicolon_to_colon'     => true,
        'switch_case_space'                  => true,
        'ternary_operator_spaces'            => true,
        'ternary_to_null_coalescing'         => true,
        'trailing_comma_in_multiline_array'  => false,
        'trim_array_spaces'                  => true,
        'unary_operator_spaces'              => true,
        'whitespace_after_comma_in_array'    => true,
        'yoda_style'                         => false
    ]);
