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

namespace Blate\Tests\Utils;

class AnyKeys
{
	public function __call($name, $arguments)
	{
		return $this;
	}

	public function __get($name)
	{
		return $this;
	}

	public function __isset($name)
	{
		return true;
	}
}
