<?php
/**
 * SPDX-FileCopyrightText: 2010 - 2024 the Friendica project
 *
 * SPDX-License-Identifier: CC0-1.0
 */

declare(strict_types=1);

require_once __DIR__ . '/bin/dev/php-cs-fixer/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__)
	->notPath('addon')
	->notPath('bin/dev')
	->notPath('config')
	->notPath('doc')
	->notPath('images')
	->notPath('mods')
	->notPath('spec')
	->notPath('vendor')
	->notPath('view/asset')
	->notPath('lang')
	->notPath('view/smarty3/compiled');

$config = new PhpCsFixer\Config();
return $config
	->setRules([
		'@PER-CS3x0'              => true,
		'align_multiline_comment' => true,
		'binary_operator_spaces' => [
			'default'   => 'single_space',
			'operators' => [
				'=>' => 'align_single_space_minimal',
				'='  => 'align_single_space_minimal',
				'??' => 'align_single_space_minimal',
			],
		],
		'braces_position'        => [
			'anonymous_classes_opening_brace'  => 'same_line',
			'control_structures_opening_brace' => 'same_line',
			'functions_opening_brace'          => 'next_line_unless_newline_at_signature_end',
		],
		'function_declaration' => [
			'closure_function_spacing' => 'one',
		],
		'list_syntax'      => [
			'syntax' => 'short',
		],
		'no_unused_imports'                  => true,
		'single_import_per_statement'        => true,
		'ternary_operator_spaces'            => false,
		'new_with_parentheses' => true,
	])
	->setFinder($finder)
	->setIndent("\t");
