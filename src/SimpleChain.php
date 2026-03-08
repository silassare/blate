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
use ArrayObject;
use Blate\Exceptions\BlateRuntimeException;
use ReflectionProperty;

/**
 * Class SimpleChain.
 *
 * Fluent property/method resolver used at template render time.
 *
 * Every template variable expression compiles to a chain of SimpleChain calls:
 *   {foo.bar}  ->  $context->chain()->get('foo')->get('bar')->val()
 *
 * Key resolution order for get():
 *   1. Method on the source object: $source->$key()
 *   2. Callable value at key (closure stored in array or object property)
 *   3. Static property via reflection
 *   4. Instance property / array key
 *
 * val() returns the final resolved value.
 * call() invokes the current value as a callable with the supplied arguments.
 */
class SimpleChain
{
	private mixed $current;

	private bool $is_head = true;

	public function __construct(private DataContext $data_context) {}

	public function val(): mixed
	{
		return $this->current;
	}

	/**
	 * @param $key
	 *
	 * @return $this
	 *
	 * @throws BlateRuntimeException
	 */
	public function get($key): self
	{
		if ($this->is_head) {
			$this->is_head = false;
			$val           = $this->data_context->get($key);
		} else {
			$source = $this->current;

			if (!self::has($source, $key, $val)) {
				throw new BlateRuntimeException(\sprintf(Message::CHAIN_UNDEFINED_KEY, $key, \get_debug_type($source)));
			}
		}

		if ($val instanceof DataContext) {
			return $val->chain();
		}

		$this->current = $val;

		return $this;
	}

	/**
	 * @param mixed ...$args
	 *
	 * @return $this
	 *
	 * @throws BlateRuntimeException
	 */
	public function call(...$args): static
	{
		if (!\is_callable($this->current)) {
			throw new BlateRuntimeException(\sprintf(Message::CHAIN_VALUE_NOT_A_CALLABLE, \get_debug_type($this->current)));
		}

		$this->current = \call_user_func_array($this->current, $args);

		return $this;
	}

	public static function has(mixed $source, mixed $key, mixed &$value): bool
	{
		if (null === $source) {
			return false;
		}

		if (
			((\is_array($source) || $source instanceof ArrayObject) && (isset($source[$key]) || \array_key_exists($key, (array) $source)))
			|| ($source instanceof ArrayAccess && isset($source[$key]))
		) {
			$value = $source[$key];

			return true;
		}

		if (\is_object($source)) {
			if (\property_exists($source, $key)) {
				$rp    = new ReflectionProperty($source, $key);
				$value = $rp->isStatic() ? $rp->getValue() : $source->{$key};

				return true;
			}

			if (\is_callable([$source, $key])) {
				$value = static function (...$args) use ($source, $key) {
					return \call_user_func_array([$source, $key], $args);
				};

				return true;
			}

			$as_array = (array) $source;

			if (\array_key_exists($key, $as_array)) {
				$value = $as_array[$key];

				return true;
			}
		}

		return false;
	}
}
