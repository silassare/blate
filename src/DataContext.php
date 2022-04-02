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

namespace Blate;

/**
 * Class DataContext.
 */
class DataContext
{
	private array $stack = [];

	/**
	 * DataContext constructor.
	 *
	 * @param \Blate\Blate $blate
	 */
	public function __construct(array|object $data, private Blate $blate)
	{
		$this->stack[] = $data;
	}

	/**
	 * @return \Blate\Blate
	 */
	public function getBlate(): Blate
	{
		return $this->blate;
	}

	/**
	 * @return $this
	 */
	public function newContext(): self
	{
		$this->stack[] = [];

		return $this;
	}

	/**
	 * @return $this
	 */
	public function popContext(): self
	{
		\array_pop($this->stack);

		return $this;
	}

	/**
	 * @return \Blate\SimpleChain
	 */
	public function chain(): SimpleChain
	{
		return new SimpleChain($this);
	}

	public function get(mixed $key): mixed
	{
		$i = \count($this->stack);

		while ($i--) {
			$ctx = $this->stack[$i];

			$found = SimpleChain::access($ctx, $key, $value);

			if ($found) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * @return $this
	 */
	public function set(mixed $key, mixed $value): self
	{
		$n                          = \count($this->stack);
		$this->stack[$n - 1][$key]  = $value;

		return $this;
	}

	/**
	 * Wrapper for htmlentities().
	 */
	public function noHTML(mixed $untrusted): string
	{
		return \htmlentities((string) $untrusted, \ENT_QUOTES, 'UTF-8');
	}
}
