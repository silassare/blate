<?php

/**
 * Copyright (c) 2021-present, Emile Silas Sare
 *
 * This file is part of Blate package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

// A tree that ends, which is what makes the self-import above a recursion and not a cycle: the
// template repeats, the data does not.
return [
	'nodes' => [
		[
			'label'    => 'a',
			'children' => [
				['label' => 'a1', 'children' => null],
				['label' => 'a2', 'children' => [['label' => 'a2x', 'children' => null]]],
			],
		],
		['label' => 'b', 'children' => null],
	],
];
