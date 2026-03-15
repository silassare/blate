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

use ArrayAccess;
use LogicException;

/**
 * Class GlobalVarsContext.
 *
 * Holds the global variable registry for Blate: both static values and
 * computed (lazy, factory-based) values.
 *
 * Implements ArrayAccess so it can be pushed onto the DataContext stack and
 * resolved transparently by SimpleChain::has() without any modification to
 * that hot-path resolver.
 *
 * Computed vars: the factory callable is invoked on each access with no
 * arguments and no memoization. Use Blate::scope() inside the factory when
 * the current render context is needed.
 *
 * Resolution precedence (offsetGet):
 *   static value (registered via registerVar) over computed factory, so that a
 *   static re-registration of a previously computed name replaces the factory.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class GlobalVarsContext implements ArrayAccess
{
	/**
	 * Static global variables: name -> value.
	 *
	 * @var array<string, mixed>
	 */
	private array $vars = [];

	/**
	 * Computed global variable factories: name -> callable(): mixed.
	 *
	 * @var array<string, callable(): mixed>
	 */
	private array $computed = [];

	/**
	 * Names registered as non-editable (constant).
	 *
	 * @var array<string, true>
	 */
	private array $constants = [];

	/**
	 * Returns true when the name has been registered as non-editable.
	 */
	public function isConst(string $name): bool
	{
		return $this->constants[$name] ?? false;
	}

	/**
	 * Returns true when the name resolves to a computed (factory-based) var
	 * and has NOT been overwritten by a static value.
	 */
	public function isComputed(string $name): bool
	{
		return isset($this->computed[$name]) && !\array_key_exists($name, $this->vars);
	}

	/**
	 * Register (or update) a static global variable.
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @param bool   $editable
	 */
	public function registerVar(string $name, mixed $value, bool $editable): void
	{
		$this->vars[$name] = $value;

		if (!$editable) {
			$this->constants[$name] = true;
		}
	}

	/**
	 * Register (or update) a computed global variable.
	 *
	 * The factory is called on every access with no arguments. No memoization.
	 *
	 * @param string            $name
	 * @param callable(): mixed $factory
	 * @param bool              $editable
	 */
	public function registerComputed(string $name, callable $factory, bool $editable): void
	{
		unset($this->vars[$name]);
		$this->computed[$name] = $factory;

		if (!$editable) {
			$this->constants[$name] = true;
		}
	}

	/**
	 * Returns all registered names (static and computed, deduplicated).
	 *
	 * @return list<string>
	 */
	public function getNames(): array
	{
		return \array_values(\array_unique(\array_merge(\array_keys($this->vars), \array_keys($this->computed))));
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetExists(mixed $offset): bool
	{
		return \array_key_exists($offset, $this->vars) || isset($this->computed[$offset]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetGet(mixed $offset): mixed
	{
		if (\array_key_exists($offset, $this->vars)) {
			return $this->vars[$offset];
		}

		if (isset($this->computed[$offset])) {
			return ($this->computed[$offset])();
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws LogicException always - GlobalVarsContext is managed by Blate, not written via array syntax
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new LogicException('GlobalVarsContext is read-only via array syntax. Use Blate::registerGlobalVar() or Blate::registerComputedGlobalVar().');
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws LogicException always
	 */
	public function offsetUnset(mixed $offset): void
	{
		throw new LogicException('GlobalVarsContext entries cannot be unset.');
	}
}
