<?php

$config = (new PhpCsFixer\Config())
  ->setIndent('  ')
  ->setRules([
    'multiline_whitespace_before_semicolons' => [
      'strategy' => 'no_multi_line',
    ],
    'align_multiline_comment' => ['comment_type' => 'all_multiline'],
    'array_indentation'       => true,
    'array_syntax'            => ['syntax' => 'short'],
    'backtick_to_shell_exec'  => true,
    'braces'                  => true,
    'indentation_type'        => true,
    'binary_operator_spaces'  => [
      'operators' => [
        '=>' => 'align',
      ],
      'default' => null,
    ],
    'method_chaining_indentation' => true,
  ]);

return $config;
