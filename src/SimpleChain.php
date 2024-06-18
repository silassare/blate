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

/**
 * Class SimpleChain.
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
			$found  = self::access($source, $key, $val);

			if (!$found) {
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

	public static function access(mixed $source, mixed $key, mixed &$value): bool
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
				$value = $source::${$key} ?? $source->{$key};

				return true;
			}

			if (\array_key_exists($key, (array) $source)) {
				$value = $source[$key];

				return true;
			}

			if (\is_callable([$source, $key])) {
				$value = static function (...$args) use ($source, $key) {
					return \call_user_func_array([$source, $key], $args);
				};

				return true;
			}
		}

		return false;
	}
}
