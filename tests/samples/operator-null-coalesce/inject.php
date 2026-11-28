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

// 'a' is set; 'b', 'missing', 'x', 'y' are all absent (context returns null). 'user' has a name and
// no title: a key missing below the top level is what `??` answers for too, at any depth.
return [
	'a'    => 'hello',
	'x'    => 42,
	'user' => ['name' => 'Ada'],
];
