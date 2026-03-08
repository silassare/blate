<?php

declare(strict_types=1);

use OLIUP\CS\PhpCS;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create();

$finder->in([
	__DIR__ . '/src',
	__DIR__ . '/tests',
])->notPath('snapshots')->notPath('blate_cache');

$header = <<<'EOF'
Copyright (c) 2021-present, Emile Silas Sare

This file is part of Blate package.

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$rules = [
	'header_comment' => [
		'header'       => $header,
		'comment_type' => 'PHPDoc',
		'separate'     => 'both',
		'location'     => 'after_open'
	],
];

return (new PhpCS())->mergeRules($finder, $rules)
	->setRiskyAllowed(true)
	->setParallelConfig(ParallelConfigFactory::detect());
