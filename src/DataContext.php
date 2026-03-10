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

use Blate\Helpers\Helpers;
use LogicException;

/**
 * Class DataContext.
 *
 * Runtime variable scope stack for template rendering.
 *
 * The stack always has at least three layers:
 *   [0] helpers layer   -- registered Blate helpers (callables)
 *   [1] global vars     -- values registered via Blate::registerGlobalVar()
 *   [2] user data       -- the data array/object passed to render()
 *   [3..n] scope layers -- pushed for each {@each} and {@scoped} block
 *
 * Variable resolution (get()) searches layers from top to bottom so that
 * inner scopes shadow outer ones.
 *
 * set() writes to the topmost (innermost) scope layer.
 * newContext() / popContext() push and pop additional scope layers.
 */
class DataContext
{
	/**
	 * @var array<int, array|object>
	 */
	private array $stack = [];

	/**
	 * DataContext constructor.
	 *
	 * @param array|object $data
	 * @param Blate        $blate
	 */
	public function __construct(array|object $data, private Blate $blate)
	{
		if ($data instanceof self) {
			$this->stack = [...$data->stack];
		} else {
			$this->stack[] = Blate::getHelpers();
			$this->stack[] = Blate::getGlobalVars();
			$this->stack[] = $data;
		}
	}

	/**
	 * @return Blate
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
	 * @param string $location compile-time source location 'line:index'
	 *
	 * @return SimpleChain
	 */
	public function chain(string $location = ''): SimpleChain
	{
		return new SimpleChain($this, $location);
	}

	public function get(mixed $key): mixed
	{
		$i = \count($this->stack);

		while ($i--) {
			$ctx = $this->stack[$i];

			$found = SimpleChain::has($ctx, $key, $value);

			if ($found) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Resolves a key exclusively from the helpers layer (stack[0]).
	 *
	 * Unlike get(), this method ignores all scope layers and user data,
	 * making it immune to user-data shadowing. Used by the $name syntax
	 * and by pipe filters.
	 *
	 * @param mixed $key the helper name to look up
	 *
	 * @return mixed the helper callable, or null if not registered
	 */
	public function getHelper(mixed $key): mixed
	{
		$found = SimpleChain::has($this->stack[0], $key, $value);

		return $found ? $value : null;
	}

	/**
	 * @return $this
	 */
	public function set(mixed $key, mixed $value): self
	{
		$n = \count($this->stack);

		if (0 === $n) {
			throw new LogicException('DataContext stack is empty.');
		}

		$this->stack[$n - 1][$key] = $value;

		return $this;
	}

	/**
	 * Alias for {@see Helpers::escapeHtml}.
	 */
	public function noHTML(mixed $untrusted): string
	{
		return Helpers::escapeHtml($untrusted);
	}
}
