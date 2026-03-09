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

// The 'upper' key in user data would shadow the registered 'upper' helper
// when accessed via {upper(...)}, but must NOT shadow it when
// accessed via {$upper(...)} or via a pipe filter {... | upper}.
return [
	'name'  => 'hello',
	'upper' => static fn () => 'HACKED',
];
