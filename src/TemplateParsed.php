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

use Blate\Exceptions\BlateException;
use LogicException;

/**
 * Class TemplateParsed.
 */
abstract class TemplateParsed
{
	protected array $injected_slots = [];

	public function hasInjectedSlot(string $name): bool
	{
		return isset($this->injected_slots[$name]);
	}

	public function injectSlot(string $name, callable $slot): static
	{
		$this->injected_slots[$name] = $slot;

		return $this;
	}

	public function renderInjectedSlot(string $name, DataContext $context): void
	{
		if (!$this->hasInjectedSlot($name)) {
			throw new LogicException(\sprintf('Unknown slot %s', $name));
		}

		$fn = $this->injected_slots[$name];

		$fn($context);
	}

	/**
	 * @param \Blate\Blate $to_extends
	 *
	 * @throws \Blate\Exceptions\BlateException
	 *
	 * @return \Blate\DataContext
	 */
	public function createExtendsContext(Blate $to_extends, mixed $data): DataContext
	{
		if (!\is_array($data) && !\is_object($data)) {
			throw new BlateException(\sprintf('Invalid context data type, found "%s" while expecting array|object.', \get_debug_type($data)));
		}

		return new DataContext($data, $to_extends);
	}

	/**
	 * @param \Blate\DataContext $context
	 */
	abstract public function build(DataContext $context): void;
}
